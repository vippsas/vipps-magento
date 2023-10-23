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
namespace Vipps\Payment\GatewayEcomm\Data;

use Magento\Framework\DataObject;

/**
 * Class Aggregate
 * @package Vipps\Payment\GatewayEcomm\Data
 * @SuppressWarnings(PHPMD.BooleanGetMethodName)
 */
class Aggregate extends DataObject
{
    /**
     * @var string
     */
    const CANCELLED_AMOUNT = 'cancelledAmount';
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
    const AUTHORIZED_AMOUNT = 'authorizedAmount';

    /**
     * @return string
     */
    public function getCancelledAmount(): Amount
    {
        return $this->getData(self::CANCELLED_AMOUNT);
    }

    /**
     * @return string
     */
    public function getCapturedAmount(): Amount
    {
        return $this->getData(self::CAPTURED_AMOUNT);
    }

    /**
     * @return string
     */
    public function getRefundedAmount(): Amount
    {
        return $this->getData(self::REFUNDED_AMOUNT);
    }

    /**
     * @return string
     */
    public function getAuthorizedAmount(): Amount
    {
        return $this->getData(self::AUTHORIZED_AMOUNT);
    }
}
