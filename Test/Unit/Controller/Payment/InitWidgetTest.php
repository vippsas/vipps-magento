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

namespace Vipps\Payment\Test\Unit\Controller\Payment;

use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Controller\Payment\InitWidget;
use Vipps\Payment\GatewayEpayment\Config\Config;
use Vipps\Payment\Model\Checkout\LastOrderSession;
use Vipps\Payment\Model\Express\CartRestoreCookie;
use Vipps\Payment\Model\Express\LivePaymentDecision;
use Vipps\Payment\Model\Express\LivePaymentGuard;
use Vipps\Payment\Model\Express\LivePaymentMessages;

/**
 * The widget gets the return URL so it can repeat it in the main tab, where Fallback has the shopper's session.
 *
 * @package Vipps\Payment\Test\Unit\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class InitWidgetTest extends TestCase
{
    private array $data = [];
    private ?int $cartId = 42;
    private int $itemsCount = 1;

    public function testReturnsThePaymentAndItsReturnUrl(): void
    {
        $this->execute(['redirectUrl' => 'https://pay-mt.vipps.no/x', 'reference' => '000450210']);

        $this->assertSame([
            'paymentUrl' => 'https://pay-mt.vipps.no/x',
            'returnUrl' => 'https://store/vipps/payment/fallback/reference/000450210/',
        ], $this->data);
    }

    public function testFallsBackToTheCartReferenceForTheReturnUrl(): void
    {
        $this->execute(['redirectUrl' => 'https://pay-mt.vipps.no/x']);

        $this->assertSame('https://store/vipps/payment/fallback/reference/000450209/', $this->data['returnUrl']);
    }

    /**
     * Without a cart in the session the payment code would fail with an \Error and a bare 500.
     */
    public function testAnEmptyCartGoesToTheCartPageWithoutStartingAPayment(): void
    {
        $this->itemsCount = 0;

        $this->execute(['redirectUrl' => 'https://pay-mt.vipps.no/x'], false);

        $this->assertSame('https://store/checkout/cart/reference//', $this->data['redirectUrl']);
        $this->assertSame($this->data['redirectUrl'], $this->data['redirect']);
        $this->assertArrayNotHasKey('paymentUrl', $this->data);
    }

    public function testASessionWithoutACartIsTreatedAsEmpty(): void
    {
        $this->cartId = null;

        $this->execute(['redirectUrl' => 'https://pay-mt.vipps.no/x'], false);

        $this->assertArrayNotHasKey('paymentUrl', $this->data);
    }

    private function execute(array $paymentResponse, bool $expectPayment = true): void
    {
        $result = $this->createMock(JsonResult::class);
        $result->method('setData')->willReturnCallback(function ($data) use ($result) {
            $this->data = $data;
            return $result;
        });
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->willReturn($result);

        $cart = $this->createMock(Quote::class);
        $cart->method('getId')->willReturn($this->cartId);
        $cart->method('getItemsCount')->willReturn($this->itemsCount);
        $cart->method('getCheckoutMethod')->willReturn('guest');
        $cart->method('getReservedOrderId')->willReturn('000450209');

        $checkoutSession = $this->createMock(Session::class);
        $checkoutSession->method('getQuote')->willReturn($cart);
        $checkoutSession->method('getQuoteId')->willReturn(42);

        $config = $this->createMock(Config::class);
        $config->method('getValue')->willReturn(true);

        $guard = $this->createMock(LivePaymentGuard::class);
        $guard->expects($expectPayment ? $this->once() : $this->never())->method('initiate')
            ->willReturn(LivePaymentDecision::proceed($paymentResponse));

        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            fn ($path, $params = []) => 'https://store/' . $path . '/reference/' . ($params['reference'] ?? '') . '/'
        );

        (new InitWidget(
            $resultFactory,
            $checkoutSession,
            $this->createMock(SessionManagerInterface::class),
            $this->createMock(CommandManagerInterface::class),
            $config,
            $this->createMock(CheckoutHelper::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CartRestoreCookie::class),
            $guard,
            $this->createMock(LastOrderSession::class),
            $this->createMock(LivePaymentMessages::class),
            $this->createMock(ManagerInterface::class),
            $urlBuilder
        ))->execute();
    }
}
