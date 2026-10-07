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

namespace Vipps\Payment\Test\Unit\Model\Quote;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote as Cart;
use Magento\Quote\Model\Quote\Payment as CartPayment;
use Magento\Sales\Api\OrderManagementInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Api\Data\QuoteStatusInterface;
use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\Model\OrderLocator;
use Vipps\Payment\Model\PaymentProvider;
use Vipps\Payment\Model\Quote as VippsQuote;
use Vipps\Payment\Model\Quote\Attempt;
use Vipps\Payment\Model\Quote\AttemptManagement;
use Vipps\Payment\Model\Quote\CancelFacade;
use Vipps\Payment\Model\QuoteRepository;

/**
 * Terminating a payment because the shopper started a new one must never void a payment they approved,
 * and must never take a payment that may still be approved out of the order fetch queue.
 *
 * @package Vipps\Payment\Test\Unit\Model\Quote
 */
class CancelFacadeTerminateTest extends TestCase
{
    private const REFERENCE = '000000001';

    private CommandManagerInterface|MockObject $commandManager;
    private PaymentProvider|MockObject $paymentProvider;
    private QuoteRepository|MockObject $quoteRepository;
    private AttemptManagement|MockObject $attemptManagement;
    private VippsQuote|MockObject $vippsQuote;
    private CartPayment|MockObject $cartPayment;

    protected function setUp(): void
    {
        $this->commandManager = $this->createMock(CommandManagerInterface::class);
        $this->paymentProvider = $this->createMock(PaymentProvider::class);
        $this->quoteRepository = $this->createMock(QuoteRepository::class);
        $this->attemptManagement = $this->createMock(AttemptManagement::class);
        $this->attemptManagement->method('createAttempt')->willReturn($this->createMock(Attempt::class));

        $this->vippsQuote = $this->createMock(VippsQuote::class);
        $this->vippsQuote->method('getReservedOrderId')->willReturn(self::REFERENCE);
        $this->vippsQuote->method('getQuoteId')->willReturn(42);

        $this->cartPayment = $this->createMock(CartPayment::class);
    }

    public function testAsksVippsToCancelOnlyWhileUnapprovedAndMarksItOnceTerminated(): void
    {
        $this->commandManager->expects($this->once())->method('cancel')
            ->with($this->cartPayment, ['reference' => self::REFERENCE, 'cancel_transaction_only' => true]);
        $this->paymentProvider->expects($this->once())->method('get')
            ->with(self::REFERENCE, true)
            ->willReturn(new Payment([Payment::STATE => Payment::STATE_TERMINATED]));

        $this->vippsQuote->expects($this->once())->method('setStatus')
            ->with(QuoteStatusInterface::STATUS_CANCELED);
        $this->quoteRepository->expects($this->once())->method('save')->with($this->vippsQuote);

        $this->assertTrue($this->facade()->terminateIfCreated($this->vippsQuote));
    }

    public function testAPaymentApprovedInTheMeantimeIsLeftAsItIs(): void
    {
        $this->paymentProvider->method('get')
            ->willReturn(new Payment([Payment::STATE => Payment::STATE_AUTHORIZED]));

        $this->vippsQuote->expects($this->never())->method('setStatus');
        $this->quoteRepository->expects($this->never())->method('save');

        $this->assertFalse($this->facade()->terminateIfCreated($this->vippsQuote));
    }

    /**
     * cancel() marks a failure cancel_failed, which takes the payment out of the queue. Here the payment
     * may still be approved by the shopper, so it has to stay in it.
     */
    public function testAFailureLeavesThePaymentInTheQueue(): void
    {
        $this->commandManager->method('cancel')->willThrowException(new \RuntimeException('timeout'));

        $this->vippsQuote->expects($this->never())->method('setStatus');
        $this->quoteRepository->expects($this->never())->method('save');
        $this->attemptManagement->expects($this->once())->method('save');

        $this->assertFalse($this->facade()->terminateIfCreated($this->vippsQuote));
    }

    private function facade(): CancelFacade
    {
        $cart = $this->createMock(Cart::class);
        $cart->method('getPayment')->willReturn($this->cartPayment);

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('get')->with(42)->willReturn($cart);

        return new CancelFacade(
            $this->commandManager,
            $this->createMock(OrderManagementInterface::class),
            $this->quoteRepository,
            $this->attemptManagement,
            $cartRepository,
            $this->createMock(OrderLocator::class),
            $this->createMock(LoggerInterface::class),
            $this->paymentProvider
        );
    }
}
