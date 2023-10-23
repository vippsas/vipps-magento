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
 * Class PaymentDetails
 * @package Vipps\Payment\GatewayEcomm\Data
 */
class PaymentDetails extends DataObject
{
    /**
     * @var string
     */
    const AMOUNT = 'amount';
    /**
     * @var string
     */
    const STATE = 'state';
    /**
     * @var string
     */
    const AGGREGATE = 'aggregate';
    /**
     * @var string
     */
    const STATE_CREATED = 'created';
    /**
     * @var string
     */
    const STATE_AUTHORIZED = 'authorized';
    /**
     * @var string
     */
    const STATE_TERMINATED = 'terminated';

    /**
     * @return Amount
     */
    public function getAmount(): Amount
    {
        return $this->getData(self::AMOUNT);
    }

    /**
     * @return string
     */
    public function getState()
    {
        return strtolower((string)$this->getData(self::STATE));
    }

    /**
     * @return
     */
    public function getAggregate(): Aggregate
    {
        return $this->getData(self::AGGREGATE);
    }

    public function isCreated()
    {
        return $this->getState() === self::STATE_CREATED;
    }

    public function isAuthorised()
    {
        return $this->getState() === self::STATE_AUTHORIZED;
    }

    public function isTerminated()
    {
        return $this->getState() === self::STATE_TERMINATED;
    }
}
