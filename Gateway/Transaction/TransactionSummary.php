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
namespace Vipps\Payment\Gateway\Transaction;

use Magento\Framework\DataObject;

/**
 * Class TransactionSummary
 * @package Vipps\Payment\Gateway\Transaction
 */
class TransactionSummary extends DataObject
{
    /**
     * @var string
     */
    const CAPTURED_AMOUNT = 'capturedAmount';

    /**
     * @var string
     */
    const REFUNDED_AMOUNT = 'refundedAmount';

    /**
     * @var string
     */
    const REMAINING_AMOUNT_TO_CAPTURE = 'remainingAmountToCapture';

    /**
     * @var string
     */
    const REMAINING_AMOUNT_TO_REFUND = 'remainingAmountToRefund';

    /**
     * @return string
     */
    public function getCapturedAmount()
    {
        return $this->getData(self::CAPTURED_AMOUNT);
    }

    /**
     * @return string
     */
    public function getRefundedAmount()
    {
        return $this->getData(self::REFUNDED_AMOUNT);
    }

    /**
     * @return string
     */
    public function getRemainingAmountToCapture()
    {
        return $this->getData(self::REMAINING_AMOUNT_TO_CAPTURE);
    }

    /**
     * @return string
     */
    public function getRemainingAmountToRefund()
    {
        return $this->getData(self::REMAINING_AMOUNT_TO_REFUND);
    }

    /**
     * @return bool
     */
    public function hasCapturedAmount()
    {
        return $this->hasData(self::CAPTURED_AMOUNT);
    }

    /**
     * @return bool
     */
    public function hasRefundedAmount()
    {
        return $this->hasData(self::REFUNDED_AMOUNT);
    }

    /**
     * @return bool
     */
    public function hasRemainingAmountToCapture()
    {
        return $this->hasData(self::REMAINING_AMOUNT_TO_CAPTURE);
    }

    /**
     * @return bool
     */
    public function hasRemainingAmountToRefund()
    {
        return $this->hasData(self::REMAINING_AMOUNT_TO_REFUND);
    }
}
