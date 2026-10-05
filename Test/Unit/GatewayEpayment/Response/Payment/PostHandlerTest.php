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

namespace Vipps\Payment\Test\Unit\GatewayEpayment\Response\Payment;

use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\GatewayEpayment\Request\SubjectReader;
use Vipps\Payment\GatewayEpayment\Response\Payment\PostHandler;
use Vipps\Payment\Model\QuoteFactory;
use Vipps\Payment\Model\QuoteRepository;

/**
 * The transfer body is the request we sent, so the Express callback token has to be read from where
 * AmountBuilder put it, otherwise nothing is stored and the shipping callback cannot be authenticated.
 *
 * @package Vipps\Payment\Test\Unit\GatewayEpayment\Response\Payment
 */
class PostHandlerTest extends TestCase
{
    public function testStoresTheExpressCallbackToken(): void
    {
        $token = 'a3f1c2d4e5b6978877665544332211ff';
        $body = [
            'reference' => '1200337124',
            'shipping' => ['dynamicOptions' => ['callbackAuthorizationToken' => $token]],
        ];

        $this->assertSame($token, $this->handle($body));
    }

    public function testStoresNoTokenForARegularPayment(): void
    {
        $this->assertSame('', $this->handle(['reference' => '1200337124']));
    }

    private function handle(array $requestBody): string
    {
        $cart = $this->createMock(Quote::class);
        $cart->method('getId')->willReturn(42);

        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getQuote')->willReturn($cart);

        $order = $this->createMock(OrderAdapterInterface::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getOrderIncrementId')->willReturn('1200337124');

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);
        $paymentDO->method('getOrder')->willReturn($order);

        $transfer = $this->createMock(TransferInterface::class);
        $transfer->method('getBody')->willReturn($requestBody);

        $storedToken = null;
        $vippsQuote = $this->createMock(QuoteInterface::class);
        $vippsQuote->method('setAuthToken')->willReturnCallback(
            function (string $value) use (&$storedToken, $vippsQuote) {
                $storedToken = $value;
                return $vippsQuote;
            }
        );

        $quoteFactory = $this->createMock(QuoteFactory::class);
        $quoteFactory->method('create')->willReturn($vippsQuote);

        $quoteRepository = $this->createMock(QuoteRepository::class);
        $quoteRepository->expects($this->once())->method('save')->with($vippsQuote);

        $handler = new PostHandler(
            new SubjectReader(),
            $quoteFactory,
            $quoteRepository,
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(CartRepositoryInterface::class)
        );
        $handler->handle(['transferObject' => $transfer, 'payment' => $paymentDO], []);

        $this->assertNotNull($storedToken, 'setAuthToken() was not called');

        return $storedToken;
    }
}
