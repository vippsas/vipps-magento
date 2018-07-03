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
namespace Vipps\Payment\Gateway\Transaction;

use Magento\Framework\DataObject;

/**
 * Class TransactionInfo
 * @package Vipps\Payment\Gateway\Transaction
 */
class TransactionInfo extends DataObject
{
    /**
     * @var string
     */
    const AMOUNT = 'amount';

    /**
     * @var string
     */
    const STATUS = 'status';

    /**
     * @var string
     */
    const TIME_STAMP = 'timeStamp';

    /**
     * @var string
     */
    const TRANSACTION_ID = 'transactionId';

    /**
     * @var string
     */
    const TRANSACTION_TEXT = 'transactionText';

    /**
     * @return string
     */
    public function getAmount()
    {
        return $this->getData(self::AMOUNT);
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return strtolower($this->getData(self::STATUS));
    }

    /**
     * @return string`
     */
    public function getTimeStamp()
    {
        return $this->getData(self::TIME_STAMP);
    }

    /**
     * @return string
     */
    public function getTransactionId()
    {
        return $this->getData(self::TRANSACTION_ID);
    }

    /**
     * @return string
     */
    public function getTransactionText()
    {
        return $this->getData(self::TRANSACTION_TEXT);
    }

    /**
     * @return bool
     */
    public function hasAmount()
    {
        return $this->hasData(self::AMOUNT);
    }

    /**
     * @return bool
     */
    public function hasStatus()
    {
        return $this->hasData(self::STATUS);
    }

    /**
     * @return bool`
     */
    public function hasTimeStamp()
    {
        return $this->hasData(self::TIME_STAMP);
    }

    /**
     * @return bool
     */
    public function hasTransactionId()
    {
        return $this->hasData(self::TRANSACTION_ID);
    }

    /**
     * @return bool
     */
    public function hasTransactionText()
    {
        return $this->hasData(self::TRANSACTION_TEXT);
    }
}
