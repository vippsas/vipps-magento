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

namespace Vipps\Payment\Test\Unit\Model\Express;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\GatewayEpayment\Config\Config;
use Vipps\Payment\Model\Express\CartRestorer;
use Vipps\Payment\Model\Quote as VippsQuote;
use Vipps\Payment\Model\QuoteRepository;

/**
 * The shopper's browser coming back is not proof that the payment is over, so the guarantee
 * under test is that restoring a cart never writes off the payment behind it.
 *
 * @package Vipps\Payment\Test\Unit\Model\Express
 */
class CartRestorerTest extends TestCase
{
    private const QUOTE_ID = 42;

    /** @var QuoteRepository|\PHPUnit\Framework\MockObject\MockObject */
    private $vippsQuoteRepository;

    /** @var CartRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $cartRepository;

    /** @var ManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $messageManager;

    /** @var VippsQuote|\PHPUnit\Framework\MockObject\MockObject */
    private $vippsQuote;

    /** @var Quote|\PHPUnit\Framework\MockObject\MockObject */
    private $cart;

    /** @var CartRestorer */
    private $cartRestorer;

    protected function setUp(): void
    {
        $this->vippsQuoteRepository = $this->createMock(QuoteRepository::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);

        $this->vippsQuote = $this->createMock(VippsQuote::class);
        $this->vippsQuoteRepository
            ->method('loadNewByQuote')
            ->with(self::QUOTE_ID)
            ->willReturn($this->vippsQuote);

        $this->cart = $this->createMock(Quote::class);
        $this->cartRepository->method('get')->with(self::QUOTE_ID)->willReturn($this->cart);

        $config = $this->createMock(Config::class);
        $config->method('getTitle')->willReturn('Vipps');

        $objectManager = new ObjectManager($this);
        $this->cartRestorer = $objectManager->getObject(
            CartRestorer::class,
            [
                'vippsQuoteRepository' => $this->vippsQuoteRepository,
                'cartRepository' => $this->cartRepository,
                'checkoutSession' => $this->createMock(Session::class),
                'messageManager' => $this->messageManager,
                'config' => $config
            ]
        );
    }

    /**
     * The regression this class exists to prevent. A payment the shopper is still completing
     * in the Vipps app must survive the store being shown again, so the monitoring quote is
     * left for FetchOrderFromVipps to resolve against the real payment state.
     */
    public function testNeverWritesOffThePaymentBehindTheCart(): void
    {
        $this->vippsQuote->expects($this->never())->method('setStatus');
        $this->vippsQuoteRepository->expects($this->never())->method('save');

        $this->assertTrue($this->cartRestorer->restore(self::QUOTE_ID));
    }

    public function testHandsTheCartBackToTheShopper(): void
    {
        $this->cart->expects($this->once())->method('setIsActive')->with(true);
        $this->cart->expects($this->once())->method('setReservedOrderId')->with(null);
        $this->cartRepository->expects($this->once())->method('save')->with($this->cart);

        $this->assertTrue($this->cartRestorer->restore(self::QUOTE_ID));
    }

    /**
     * Telling a shopper mid-payment that it was cancelled is what prompted them to start a
     * second payment, so the message must not claim a cancellation that has not happened.
     */
    public function testDoesNotTellTheShopperThePaymentWasCancelled(): void
    {
        $this->messageManager->expects($this->never())->method('addWarningMessage');
        $this->messageManager->expects($this->once())->method('addNoticeMessage');

        $this->cartRestorer->restore(self::QUOTE_ID);
    }

    public function testReturnsFalseWhenThereIsNoPendingPayment(): void
    {
        $repository = $this->createMock(QuoteRepository::class);
        $repository->method('loadNewByQuote')->willThrowException(new NoSuchEntityException());

        $objectManager = new ObjectManager($this);
        /** @var CartRestorer $cartRestorer */
        $cartRestorer = $objectManager->getObject(
            CartRestorer::class,
            [
                'vippsQuoteRepository' => $repository,
                'cartRepository' => $this->cartRepository,
                'checkoutSession' => $this->createMock(Session::class),
                'messageManager' => $this->messageManager,
                'config' => $this->createMock(Config::class)
            ]
        );

        $this->cartRepository->expects($this->never())->method('save');

        $this->assertFalse($cartRestorer->restore(self::QUOTE_ID));
    }

    public function testReturnsFalseWithoutAQuoteId(): void
    {
        $this->vippsQuoteRepository->expects($this->never())->method('save');
        $this->cartRepository->expects($this->never())->method('save');

        $this->assertFalse($this->cartRestorer->restore(0));
    }
}
