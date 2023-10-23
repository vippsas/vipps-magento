<?php
/**
 * Copyright 2022 Vipps
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

namespace Vipps\Payment\GatewayEcomm\Request\Payment;

use Magento\Customer\Model\Session;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Vipps\Payment\GatewayEcomm\Request\SubjectReader;

/**
 * Class TransactionDataBuilder
 * @package Vipps\Payment\GatewayEcomm\Request\InitSession
 */
class AmountBuilder implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var SessionManagerInterface|Session
     */
    private SessionManagerInterface $customerSession;
    private UrlInterface $urlBuilder;


    /**
     * TransactionDataBuilder constructor.
     *
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        SubjectReader           $subjectReader,
        UrlInterface            $urlBuilder,
        SessionManagerInterface $customerSession
    ) {
        $this->subjectReader = $subjectReader;
        $this->customerSession = $customerSession;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Get related data for transaction section.
     *
     * @param array $buildSubject
     *
     * @return array[]
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        /** @var \Magento\Quote\Model\Quote\Payment $payment */
        $payment = $paymentDO->getPayment();
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $payment->getQuote();

        if (!$quote->getReservedOrderId()) {
            $quote->reserveOrderId();
        }
        $reference = $quote->getReservedOrderId();

        return [
            'amount'             => [
                'currency' => $quote->getStoreCurrencyCode(),
                'value'    => $quote->getGrandTotal() * 100
            ],
            'reference'          => $reference,
            'paymentDescription' => "%order description%",
            'paymentMethod'      => ["type" => "WALLET"],
            "userFlow"           => "WEB_REDIRECT",
            'returnUrl'          => $this->urlBuilder->getUrl('vipps/payment/fallback', ['reference' => $reference])
        ];
    }
}
