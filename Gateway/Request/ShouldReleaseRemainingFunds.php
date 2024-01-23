<?php
/**
 * Copyright 2020 Vipps
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
namespace Vipps\Payment\Gateway\Request;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;
use Vipps\Payment\Gateway\Command\PaymentDetailsProvider;

/**
 * Class Transaction
 * @package Vipps\Payment\Gateway\Request\InitiateData
 */
class ShouldReleaseRemainingFunds implements BuilderInterface
{
    use Formatter;
    
    /**
     * Transaction block name
     *
     * @var string
     */
    private static $fieldName = 'shouldReleaseRemainingFunds';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var SubjectReader
     */
    private $subjectReader;
    private PaymentDetailsProvider $paymentDetailsProvider;

    /**
     * TransactionDataBuilder constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        SubjectReader $subjectReader,
        PaymentDetailsProvider $paymentDetailsProvider
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->subjectReader = $subjectReader;
        $this->paymentDetailsProvider = $paymentDetailsProvider;
    }

    /**
     * Get merchant related data for transaction request.
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $shouldReleaseRemainingFunds = [];

        $orderId = $this->subjectReader->readPayment($buildSubject)->getOrder()->getOrderIncrementId();

        $transaction = $this->paymentDetailsProvider->get($orderId);
        if ($transaction->getTransactionSummary()->getCapturedAmount() > 0) {
            $shouldReleaseRemainingFunds[self::$fieldName] = true;
        }

        return $shouldReleaseRemainingFunds;
    }
}
