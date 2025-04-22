<?php

/**
 * Copyright 2020 Vipps
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software
 * and associated documentation files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING
 * BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON
 * INFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Vipps\Payment\Test\Integration\Controller\Payment;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Adapter\Curl;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Module\Manager;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\MockObject\MockObject;
use Vipps\Payment\Gateway\Http\Client\Curl as VippsCurl;
use Vipps\Payment\GatewayEpayment\Http\Client\PaymentCurl;
use Vipps\Payment\Model\TokenProvider;

class FallbackTest extends AbstractController
{
    /**
     * @var Curl|MockObject
     */
    private $paymentCurlMock;

    /**
     * @var Curl|MockObject
     */
    private $tokenProviderCurlMock;

    /**
     * @var VippsCurl|MockObject
     */
    private $vippsCurlMock;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var QuoteManagement
     */
    private $quoteManagement;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Manager
     */
    private $moduleManager;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenProviderCurlMock = $this->createMock(Curl::class);
        $adapterFactoryMock = $this->createMock(CurlFactory::class);
        $adapterFactoryMock->expects($this->any())->method('create')->willReturn($this->tokenProviderCurlMock);
        $tokenProvider = $this->_objectManager->create(TokenProvider::class, [
            'adapterFactory' => $adapterFactoryMock,
        ]);
        $this->_objectManager->addSharedInstance($tokenProvider, TokenProvider::class);

        $this->paymentCurlMock = $this->createMock(Curl::class);
        $adapterFactoryMock = $this->createMock(CurlFactory::class);
        $adapterFactoryMock->expects($this->any())->method('create')->willReturn($this->paymentCurlMock);
        $paymentCurl = $this->_objectManager->create(PaymentCurl::class, [
            'adapterFactory' => $adapterFactoryMock,
        ]);
        $this->_objectManager->addSharedInstance($paymentCurl, PaymentCurl::class);

        $this->vippsCurlMock = $this->createMock(Curl::class);
        $adapterFactoryMock = $this->createMock(CurlFactory::class);
        $adapterFactoryMock->expects($this->any())->method('create')->willReturn($this->vippsCurlMock);
        $vippsCurl = $this->_objectManager->create(VippsCurl::class, [
            'adapterFactory' => $adapterFactoryMock,
        ]);
        $this->_objectManager->addSharedInstance($vippsCurl, VippsCurl::class);

        $this->checkoutSession = $this->_objectManager->get(Session::class);
        $this->_objectManager->addSharedInstance($this->checkoutSession, Session::class);
        $this->quoteManagement = $this->_objectManager->create(QuoteManagement::class);
        $this->productRepository = $this->_objectManager->create(ProductRepositoryInterface::class);
        $this->moduleManager = $this->_objectManager->create(Manager::class);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/vipps/active 1
     * @magentoConfigFixture current_store payment/vipps/version vipps_payment
     * @magentoConfigFixture current_store catalog/price/scope 0
     * @magentoConfigFixture current_store currency/options/allow NOK
     * @magentoConfigFixture default/currency/options/base NOK
     * @magentoConfigFixture default/currency/options/default NOK
     * @magentoDataFixture Magento/Catalog/_files/products.php
     */
    public function testExecuteShouldSuccessfullyRestoreQuoteWithVippsPaymentApiAndDefaultCheckout()
    {
        $order = $this->prepareOrder('vipps');

        $this->tokenProviderCurlMock->expects($this->any())->method('read')->willReturn(
            "HTTP/1.1 200 Success\n\n"
            . \json_encode([
                'token_type' => 'Bearer',
                'expires_in' => '3599',
                'expires_on' => \time() + 10000,
                'ext_expires_in' => 0,
                'not_before' => \time() + 10000,
                'resource' => '00000003-0000-0000-c000-000000000000',
                'access_token' => 'token1234',
            ])
        );

        $this->vippsCurlMock->expects($this->any())->method('read')->willReturn(
            "HTTP/1.1 200 Success\n\n"
            . \json_encode([
                'orderId' => $order->getIncrementId(),
                'transactionLogHistory' => [
                    [
                        'operation' => 'CANCEL',
                        'operationSuccess' => true,
                    ],
                ],
            ])
        );

        $this->getRequest()->setParams([
            'order_id' => $order->getIncrementId(),
            'auth_token' => 'authToken1234',
        ]);
        $this->dispatch('vipps/payment/fallback');
        $this->assertSessionMessages($this->equalTo([
            __('Your order was cancelled in Vipps.'),
        ]));
        $this->assertRedirect($this->stringContains('checkout/cart'));

        $quote = $this->checkoutSession->getQuote();
        $this->assertNull($quote->getReservedOrderId());
        $this->assertEquals(1, $quote->getItemsCollection()->count());
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store payment/vipps/active 1
     * @magentoConfigFixture current_store payment/vipps/version vipps_payment
     * @magentoConfigFixture current_store catalog/price/scope 0
     * @magentoConfigFixture current_store currency/options/allow NOK
     * @magentoConfigFixture default/currency/options/base NOK
     * @magentoConfigFixture default/currency/options/default NOK
     * @magentoDataFixture Magento/Catalog/_files/products.php
     */
    public function testExecuteShouldSuccessfullyRestoreQuoteWithVippsPaymentApiAndKlarna()
    {
        if (!$this->moduleManager->isEnabled('Klarna_Kco')) {
            $this->markTestSkipped('Skipping test due to Klarna not being available/installed');
        }

        $order = $this->prepareOrder('klarna_kco');

        $this->tokenProviderCurlMock->expects($this->any())->method('read')->willReturn(
            "HTTP/1.1 200 Success\n\n"
            . \json_encode([
                'token_type' => 'Bearer',
                'expires_in' => '3599',
                'expires_on' => \time() + 10000,
                'ext_expires_in' => 0,
                'not_before' => \time() + 10000,
                'resource' => '00000003-0000-0000-c000-000000000000',
                'access_token' => 'token1234',
            ])
        );

        $this->vippsCurlMock->expects($this->any())->method('read')->willReturn(
            "HTTP/1.1 200 Success\n\n"
            . \json_encode([
                'orderId' => $order->getIncrementId(),
                'transactionLogHistory' => [
                    [
                        'operation' => 'CANCEL',
                        'operationSuccess' => true,
                    ],
                ],
            ])
        );

        $this->getRequest()->setParams([
            'order_id' => $order->getIncrementId(),
            'auth_token' => 'authToken1234',
        ]);
        $this->dispatch('vipps/payment/fallback');
        $this->assertSessionMessages($this->equalTo([
            __('Your order was cancelled in Vipps.'),
        ]));
        $this->assertRedirect($this->stringContains('checkout/cart'));

        $quote = $this->checkoutSession->getQuote();
        $this->assertNull($quote->getReservedOrderId());
        $this->assertEquals(1, $quote->getItemsCollection()->count());
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store payment/vipps/active 1
     * @magentoConfigFixture current_store payment/vipps/version mobile_epayment
     * @magentoConfigFixture current_store catalog/price/scope 0
     * @magentoConfigFixture current_store currency/options/allow NOK
     * @magentoConfigFixture default/currency/options/base NOK
     * @magentoConfigFixture default/currency/options/default NOK
     * @magentoDataFixture Magento/Catalog/_files/products.php
     */
    public function testExecuteShouldSuccessfullyRestoreQuoteWithMobilePayApiAndKlarna()
    {
        if (!$this->moduleManager->isEnabled('Klarna_Kco')) {
            $this->markTestSkipped('Skipping test due to Klarna not being available/installed');
        }

        $order = $this->prepareOrder('klarna_kco');

        $this->tokenProviderCurlMock->expects($this->any())->method('read')->willReturn(
            "HTTP/1.1 200 Success\n\n"
            . \json_encode([
                'token_type' => 'Bearer',
                'expires_in' => '3599',
                'expires_on' => \time() + 10000,
                'ext_expires_in' => 0,
                'not_before' => \time() + 10000,
                'resource' => '00000003-0000-0000-c000-000000000000',
                'access_token' => 'token1234',
            ])
        );

        $this->paymentCurlMock->expects($this->any())->method('read')->willReturn(
            "HTTP/1.1 200 Success\n\n"
            . \json_encode([
                'reference' => $order->getIncrementId(),
                'state' => 'ABORTED',
            ])
        );

        $this->getRequest()->setParams([
            'reference' => $order->getIncrementId(),
        ]);
        $this->dispatch('vipps/payment/fallback');
        $this->assertSessionMessages($this->equalTo([
            __('Your order was cancelled in MobilePay.'),
        ]));
        $this->assertRedirect($this->stringContains('checkout/cart'));

        $quote = $this->checkoutSession->getQuote();
        $this->assertNull($quote->getReservedOrderId());
        $this->assertEquals(1, $quote->getItemsCollection()->count());
    }

    /**
     * @param string $paymentMethod
     *
     * @return OrderInterface|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function prepareOrder(string $paymentMethod): ?OrderInterface
    {
        $product = $this->productRepository->get('simple');

        /** @var Quote $quote */
        $quote = $this->_objectManager->create(Quote::class);
        $quote->setCustomerIsGuest(1);
        $quote->setCustomerEmail('customer@example.com');
        $quote->setStoreId(1);
        $quote->setBaseCurrencyCode('NOK');
        $quote->setGlobalCurrencyCode('NOK');
        $quote->setQuoteCurrencyCode('NOK');
        $quote->setStoreCurrencyCode('NOK');
        $quote->addProduct($product);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setFirstname('John');
        $shippingAddress->setLastname('Doe');
        $shippingAddress->setStreet('Street');
        $shippingAddress->setCity('City');
        $shippingAddress->setPostcode(1234);
        $shippingAddress->setCountryId('NO');
        $shippingAddress->setTelephone('04012345');
        $shippingAddress->setShippingMethod('flatrate_flatrate');
        $quote->setShippingAddress($shippingAddress);

        $billingAddress = $quote->getBillingAddress();
        $billingAddress->setFirstname('John');
        $billingAddress->setLastname('Doe');
        $billingAddress->setStreet('Street');
        $billingAddress->setCity('City');
        $billingAddress->setPostcode(1234);
        $billingAddress->setCountryId('NO');
        $billingAddress->setTelephone('04012345');
        $quote->setBillingAddress($billingAddress);

        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();
        $quote->save();

        /** @var Quote\Payment $payment */
        $payment = $this->_objectManager->create(Quote\Payment::class);
        $payment->setMethod($paymentMethod);
        $payment->setQuote($quote);
        $payment->save();

        $order = $this->quoteManagement->submit($quote);

        /** @var \Vipps\Payment\Model\Quote $vippsQuote */
        $vippsQuote = $this->_objectManager->create(\Vipps\Payment\Model\Quote::class);
        $vippsQuote->setQuoteId($quote->getId());
        $vippsQuote->setOrderId($order->getEntityId());
        $vippsQuote->setReservedOrderId($order->getIncrementId());
        $vippsQuote->setAuthToken('authToken1234');
        $vippsQuote->save();

        return $order;
    }
}
