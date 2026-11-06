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
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedExceptionFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Controller\Payment\InitExpress;
use Vipps\Payment\GatewayEpayment\Config\Config;
use Vipps\Payment\Model\Checkout\LastOrderSession;
use Vipps\Payment\Model\Express\CartRestoreCookie;
use Vipps\Payment\Model\Express\LivePaymentGuard;
use Vipps\Payment\Model\Express\LivePaymentMessages;

/**
 * Without a cart in the session the payment code would fail with an \Error and a bare 500.
 *
 * @package Vipps\Payment\Test\Unit\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class InitExpressTest extends TestCase
{
    /**
     * @dataProvider emptyCartProvider
     */
    public function testAnEmptyCartGoesBackToTheCartWithoutStartingAPayment(?int $cartId, int $itemsCount): void
    {
        $cart = $this->createMock(Quote::class);
        $cart->method('getId')->willReturn($cartId);
        $cart->method('getItemsCount')->willReturn($itemsCount);

        $checkoutSession = $this->createMock(Session::class);
        $checkoutSession->method('getQuote')->willReturn($cart);

        $guard = $this->createMock(LivePaymentGuard::class);
        $guard->expects($this->never())->method('initiate');

        $messageManager = $this->createMock(ManagerInterface::class);
        $messageManager->expects($this->once())->method('addErrorMessage')
            ->with($this->callback(fn ($message) => (string)$message === 'Your cart is empty.'));

        $redirectPath = null;
        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function ($path) use (&$redirectPath, $redirect) {
            $redirectPath = $path;
            return $redirect;
        });
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->willReturn($redirect);

        $config = $this->createMock(Config::class);
        $config->method('getValue')->willReturn(true);

        (new InitExpress(
            $resultFactory,
            $checkoutSession,
            $this->createMock(SessionManagerInterface::class),
            $this->createMock(CommandManagerInterface::class),
            $this->createMock(CartRepositoryInterface::class),
            $this->createMock(LocalizedExceptionFactory::class),
            $config,
            $messageManager,
            $this->createMock(CheckoutHelper::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CartRestoreCookie::class),
            $guard,
            $this->createMock(LastOrderSession::class),
            $this->createMock(LivePaymentMessages::class)
        ))->execute();

        $this->assertSame('checkout/cart', $redirectPath);
    }

    /**
     * @return array<string, array{0: ?int, 1: int}>
     */
    public static function emptyCartProvider(): array
    {
        return [
            'no cart in the session' => [null, 0],
            'cart without items' => [42, 0],
        ];
    }
}
