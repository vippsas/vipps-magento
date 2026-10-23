<?php
/**
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

namespace Vipps\Payment\Test\Unit\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Payment\Gateway\ConfigInterface;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\GatewayEpayment\Command\PaymentDetailsProvider;
use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\GraphQl\ReturnAuthorizer;
use Vipps\Payment\GraphQl\Resolver\GetPaymentDetails;
use Vipps\Payment\Model\Transaction\StatusVisitor;

/**
 * Uses real Payment objects: the resolver used to call methods of the pre-ePayment model, which a mock hid.
 */
class GetPaymentDetailsTest extends TestCase
{
    public function testACancelledPaymentOffersItsCartBack(): void
    {
        $this->assertSame([
            'order_number' => '000000042',
            'cancelled' => true,
            'reserved' => false,
            'restore_cart' => true,
        ], $this->resolve($this->authorizer(), Payment::STATE_ABORTED));
    }

    public function testAnApprovedPaymentIsReserved(): void
    {
        $this->assertSame([
            'order_number' => '000000042',
            'cancelled' => false,
            'reserved' => true,
            'restore_cart' => false,
        ], $this->resolve($this->authorizer(), Payment::STATE_AUTHORIZED));
    }

    public function testNothingIsReadFromVippsWithoutAuthorisation(): void
    {
        $authorizer = $this->createMock(ReturnAuthorizer::class);
        $authorizer->method('authorize')->willThrowException(new GraphQlAuthorizationException(__('denied')));

        $this->expectException(GraphQlAuthorizationException::class);

        $this->resolve($authorizer, Payment::STATE_AUTHORIZED, false);
    }

    private function authorizer(): ReturnAuthorizer
    {
        return $this->createMock(ReturnAuthorizer::class);
    }

    private function resolve(ReturnAuthorizer $authorizer, string $state, bool $expectRead = true): array
    {
        $detailsProvider = $this->createMock(PaymentDetailsProvider::class);
        $detailsProvider->expects($expectRead ? $this->once() : $this->never())->method('get')->willReturn(
            new Payment([Payment::STATE => $state, Payment::REFERENCE => '000000042'])
        );

        $config = $this->createMock(ConfigInterface::class);
        $config->method('getValue')->willReturn(true);

        return (new GetPaymentDetails($detailsProvider, $config, $authorizer, new StatusVisitor()))->resolve(
            $this->createMock(Field::class),
            $this->createMock(ContextInterface::class),
            $this->createMock(ResolveInfo::class),
            null,
            ['order_number' => '000000042', 'token' => 'token']
        );
    }
}
