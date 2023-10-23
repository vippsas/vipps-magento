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
 * Class Session
 * @package Vipps\Payment\GatewayEcomm\Data
 */
class Session extends DataObject
{
    /**
     * @var string
     */
    const SESSION_ID = 'sessionId';
    /**
     * @var string
     */
    const REFERENCE = 'reference';
    /**
     * @var string
     */
    const SESSION_STATE = 'sessionState';
    /**
     * @var string
     */
    const PAYMENT_METHOD = 'paymentMethod';
    /**
     * @var string
     */
    const PAYMENT_DETAILS = 'paymentDetails';
    /**
     * @var string
     */
    const SHIPPING_DETAILS = 'shippingDetails';
    /**
     * @var string
     */
    const BILLING_DETAILS = 'billingDetails';

    /**
     * @return string
     */
    public function getSessionId()
    {
        return $this->getData(self::SESSION_ID);
    }

    /**
     * @return string
     */
    public function getReference()
    {
        return $this->getData(self::REFERENCE);
    }

    /**
     * @return string
     */
    public function getSessionState()
    {
        return $this->getData(self::SESSION_STATE);
    }

    /**
     * @return string
     */
    public function getPaymentMethod()
    {
        return $this->getData(self::PAYMENT_METHOD);
    }

    /**
     * @return string
     */
    public function getPaymentDetails(): PaymentDetails
    {
        return $this->getData(self::PAYMENT_DETAILS);
    }

    /**
     * @return string
     */
    public function getShippingDetails(): ShippingDetails
    {
        return $this->getData(self::SHIPPING_DETAILS);
    }

    /**
     * @return string
     */
    public function getBillingDetails(): BillingDetails
    {
        return $this->getData(self::BILLING_DETAILS);
    }

    public function isSessionExpired(): bool
    {
        return $this->getSessionState() === 'SessionExpired';
    }

    public function isSessionCreated(): bool
    {
        return $this->getSessionState() === 'SessionCreated';
    }

    public function isPaymentInitiated(): bool
    {
        return $this->getSessionState() === 'PaymentInitiated';
    }

    public function isPaymentSuccessful(): bool
    {
        return $this->getSessionState() === 'PaymentSuccessful';
    }

    public function isPaymentTerminated(): bool
    {
        return $this->getSessionState() === 'PaymentTerminated';
    }
}
