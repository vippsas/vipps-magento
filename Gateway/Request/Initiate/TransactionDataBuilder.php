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

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Model\Quote\Payment;
use Vipps\Payment\Gateway\Request\SubjectReader;

/**
 * Class Transaction
 * @package Vipps\Payment\Gateway\Request\InitiateData
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class TransactionDataBuilder implements BuilderInterface
{
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
        return [
            self::$transaction => [
                self::$orderId => $payment->getQuote()->getReservedOrderId()
            ]
        ];
    }
}
