<?php
/**
 * Copyright 2018 Vipps
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
namespace Vipps\Payment\Gateway\Request\Initiate;

use Magento\Payment\Helper\Formatter;
use Magento\Quote\Model\Quote\Payment;
use Vipps\Payment\Gateway\Request\SubjectReader;

/**
 * Class Transaction
 * @package Vipps\Payment\Gateway\Request\InitiateData
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class TransactionDataBuilder implements InitiateBuilderInterface
{
    use Formatter;

    /**
     * Transaction block name
     *
     * @var string
     */
    private static $transaction = 'transaction';

    /**
     * Id which uniquely identifies a payment. Maximum length is 30 alphanumeric characters.
     *
     * @var string
     */
    private static $orderId = 'orderId';

    /**
     * Amount in order. 32 Bit Integer (2147483647)
     *
     * @var string
     */
    private static $amount = 'amount';

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * TransactionDataBuilder constructor.
     *
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        SubjectReader $subjectReader
    ) {
        $this->subjectReader = $subjectReader;
    }

    /**
     * Get merchant related data for Initiate payment request.
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();
        $quote = $payment->getQuote();

        $amount = $this->subjectReader->readAmount($buildSubject);
        $amount = (int)round($this->formatPrice($amount) * 100);

        if ($buildSubject[self::PAYMENT_TYPE_KEY] == self::PAYMENT_TYPE_EXPRESS_CHECKOUT) {
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setShippingMethod(null);
            $quote->collectTotals();

            $amount = (int)round($this->formatPrice($quote->getGrandTotal()) * 100);
        }

        return [
            self::$transaction => [
                self::$orderId => $quote->getReservedOrderId(),
                self::$amount => $amount
            ]
        ];
    }
}
