<?php
/*
 * Copyright 2026 Vipps
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED
 * TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */
declare(strict_types=1);

namespace Vipps\Payment\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Vipps\Payment\GatewayEpayment\Data\Payment as DataPayment;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\Model\OrderLocator;
use Vipps\Payment\Model\Quote as VippsQuote;
use Vipps\Payment\Model\TransactionProcessor;

/**
 * Pressing Express again makes a second monitoring quote against the same cart, with its own
 * reference and no link to the first. If the shopper authorises both, each looks placeable on its
 * own, so the cart has to be the thing that is checked.
 *
 * @package Vipps\Payment\Test\Unit\Model
 */
class TransactionProcessorCartGuardTest extends TestCase
{
    private const QUOTE_ID = 1234;

    /**
     * Goes through processReservedTransaction rather than the guard alone, so that removing the
     * call site fails this test too.
     */
    public function testRefusesToPlaceASecondOrderForTheSameCart(): void
    {
        $existingOrder = $this->createMock(OrderInterface::class);
        $existingOrder->method('getIncrementId')->willReturn('000000002');

        $orderLocator = $this->createMock(OrderLocator::class);
        $orderLocator->method('get')->with('000000001')->willReturn(null);
        $orderLocator->method('getByQuoteId')->with(self::QUOTE_ID)->willReturn($existingOrder);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/already been ordered as 000000002/');

        $processor = $this->processorWith($orderLocator);
        $method = new \ReflectionMethod(TransactionProcessor::class, 'processReservedTransaction');
        $method->invoke($processor, $this->vippsQuote(), $this->createMock(DataPayment::class));
    }

    public function testAllowsACartThatHasNotBeenOrdered(): void
    {
        $orderLocator = $this->createMock(OrderLocator::class);
        $orderLocator->method('getByQuoteId')->with(self::QUOTE_ID)->willReturn(null);

        $method = new \ReflectionMethod(TransactionProcessor::class, 'assertCartHasNoOrder');
        $method->invoke($this->processorWith($orderLocator), $this->vippsQuote());

        $this->expectNotToPerformAssertions();
    }

    /**
     * The cart row goes when Magento cleans up expired quotes, because the foreign key is
     * ON DELETE SET NULL. Looking an order up by a null cart id is ambiguous, and a match would
     * block a placement that should go ahead.
     */
    public function testDoesNotBlockWhenTheCartReferenceIsGone(): void
    {
        $orderLocator = $this->createMock(OrderLocator::class);
        $orderLocator->expects($this->never())->method('getByQuoteId');

        $vippsQuote = $this->createMock(VippsQuote::class);
        $vippsQuote->method('getQuoteId')->willReturn(null);

        $method = new \ReflectionMethod(TransactionProcessor::class, 'assertCartHasNoOrder');
        $method->invoke($this->processorWith($orderLocator), $vippsQuote);
    }

    /**
     * The guard reads only the order locator, so building the processor's other collaborators
     * would couple this test to them for nothing.
     */
    private function processorWith(OrderLocator $orderLocator): TransactionProcessor
    {
        $processor = (new \ReflectionClass(TransactionProcessor::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(TransactionProcessor::class, 'orderLocator'))
            ->setValue($processor, $orderLocator);

        return $processor;
    }

    private function vippsQuote(): VippsQuote
    {
        $vippsQuote = $this->createMock(VippsQuote::class);
        $vippsQuote->method('getQuoteId')->willReturn(self::QUOTE_ID);
        $vippsQuote->method('getReservedOrderId')->willReturn('000000001');

        return $vippsQuote;
    }
}
