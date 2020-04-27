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
namespace Vipps\Payment\Gateway\Request;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;

/**
 * Class Transaction
 * @package Vipps\Payment\Gateway\Request\InitiateData
 */
class TransactionDataBuilder implements BuilderInterface
{
    use Formatter;

    /**
     * Amount in order. 32 Bit Integer (2147483647)
     *
     * @var string
     */
    private static $amount = 'amount';

    /**
     * Transaction block name
     *
     * @var string
     */
    private static $transaction = 'transaction';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * TransactionDataBuilder constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        SubjectReader $subjectReader
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->subjectReader = $subjectReader;
    }

    /**
     * Get merchant related data for transaction request.
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $transactionData = [];

        $amount = $this->subjectReader->readAmount($buildSubject);
        if ($amount) {
            $transactionData[self::$transaction][self::$amount] = (int)round($this->formatPrice($amount) * 100);
        }

        return $transactionData;
    }
}
