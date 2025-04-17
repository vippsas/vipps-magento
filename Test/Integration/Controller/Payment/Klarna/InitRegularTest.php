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

namespace Vipps\Payment\Test\Integration\Controller\Payment\Klarna;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Adapter\Curl;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\MockObject\MockObject;
use Vipps\Payment\Gateway\Http\Client\Curl as VippsCurl;
use Vipps\Payment\GatewayEpayment\Http\Client\PaymentCurl;
use Vipps\Payment\Model\TokenProvider;

class InitRegularTest extends AbstractController
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
     * @var QuoteResource
     */
    private $quoteResource;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

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
        $this->quoteResource = $this->_objectManager->create(QuoteResource::class);
        $this->productRepository = $this->_objectManager->create(ProductRepositoryInterface::class);
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
    public function testExecuteShouldSuccessfullyExecuteWithoutErrorsWithVippsPaymentApi()
    {
        $quote = $this->prepareQuote();

        /** @var Quote\Payment $payment */
        $payment = $this->_objectManager->create(Quote\Payment::class);
        $payment->setMethod('klarna_kco');
        $payment->setQuote($quote);
        $payment->save();

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

        $orderId = $this->quoteResource->getReservedOrderId($quote) + 1;
        $orderId = \str_pad($orderId, 9, '0', STR_PAD_LEFT);

        $this->vippsCurlMock->expects($this->any())->method('read')->willReturn(
            "HTTP/1.1 200 Success\n\n"
            . \json_encode([
                'orderId' => $orderId,
                'url' => 'url/to/redirect/to',
            ])
        );

        $this->checkoutSession->replaceQuote($quote);

        $this->dispatch('vipps/payment/klarna_initRegular');
        $this->assertSessionMessages($this->equalTo([]));
        $this->assertRedirect($this->stringContains('url/to/redirect/to'));
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
    public function testExecuteShouldSuccessfullyExecuteWithoutErrorsWithMobilePayApi()
    {
        $quote = $this->prepareQuote();

        /** @var Quote\Payment $payment */
        $payment = $this->_objectManager->create(Quote\Payment::class);
        $payment->setMethod('klarna_kco');
        $payment->setQuote($quote);
        $payment->save();

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

        $orderId = $this->quoteResource->getReservedOrderId($quote) + 1;
        $orderId = \str_pad($orderId, 9, '0', STR_PAD_LEFT);

        $this->paymentCurlMock->expects($this->any())->method('read')->willReturn(
            "HTTP/1.1 200 Success\n\n"
            . \json_encode([
                'reference' => $orderId,
                'redirectUrl' => 'url/to/redirect/to',
            ])
        );

        $this->checkoutSession->replaceQuote($quote);

        $this->dispatch('vipps/payment/klarna_initRegular');
        $this->assertSessionMessages($this->equalTo([]));
        $this->assertRedirect($this->stringContains('url/to/redirect/to'));
    }

    /**
     * @return Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function prepareQuote(): Quote
    {
        $product = $this->productRepository->get('simple');

        /** @var Quote $quote */
        $quote = $this->_objectManager->create(Quote::class);
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

        $quote->save();

        return $quote;
    }
}
