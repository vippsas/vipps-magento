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
 * Class Payment
 * @package Vipps\Payment\GatewayEcomm\Data
 * @SuppressWarnings(PHPMD.BooleanGetMethodName)
 */
class Payment extends DataObject
{
    /**
     * @var string
     */
    const AGGREGATE = 'aggregate';
    const AMOUNT = 'amount';
    const AUTHORISATION_TYPE = 'authorisationType';
    const STATE = 'state';
    const DIRECT_CAPTURE = 'directCapture';
    const CUSTOMER = 'customer';
    const CUSTOMER_INTERACTION = 'customerInteraction';
    const INDUSTRY_DATA = 'industryData';
    const LOGISTICS = 'logistics';
    const ORDER_INFORMATION = 'orderInformation';
    const PAYMENT_METHOD = 'paymentMethod';
    const PROFILE = 'profile';
    const PSP_REFERENCE = 'pspReference';
    const REDIRECT_URL = 'redirectUrl';
    const REFERENCE = 'reference';
    const RETURN_URL = 'returnUrl';
    const SUB_REFERENCE = 'subReference';
    const USER_FLOW = 'userFlow';
    const PAYMENT_DESCRIPTION = 'paymentDescription';
    const CALLBACK_URL = 'callbackUrl';

    /**
     * User has not yet acted upon the payment
     *
     * @var string
     */
    const STATE_CREATED = 'created';
    /**
     * User has aborted the payment before authorization
     *
     * @var string
     */
    const STATE_ABORTED = 'aborted';
    /**
     * User did not act on the payment within the payment expiration time
     *
     * @var string
     */
    const STATE_EXPIRED = 'expired';

    /**
     * User has approved the payment
     *
     * @var string
     */
    const STATE_AUTHORIZED = 'authorized';

    /**
     * Merchant has terminated the payment via the cancelPayment endpoint
     *
     * @var string
     */
    const STATE_TERMINATED = 'terminated';

    /**
     * @return Aggregate
     */
    public function getAggregate(): Aggregate
    {
        return $this->getData(self::AGGREGATE);
    }

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
    public function getAuthorisationType(): string
    {
        return (string)$this->getData(self::AUTHORISATION_TYPE);
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return \strtolower($this->getData(self::STATE));
    }

    /**
     * @return bool
     */
    public function getDirectCapture(): bool
    {
        return (bool)$this->getData(self::DIRECT_CAPTURE);
    }

    /**
     * @return Customer|null
     */
    public function getCustomer(): ?Customer
    {
        return $this->getData(self::CUSTOMER);
    }

    /**
     * @return string
     */
    public function getCustomerInteraction(): string
    {
        return (string)$this->getData(self::CUSTOMER_INTERACTION);
    }

    /**
     * @return PaymentMethod
     */
    public function getPaymentMethod(): PaymentMethod
    {
        return $this->getData(self::PAYMENT_METHOD);
    }

    /**
     * @return string
     */
    public function getPaymentDescription(): string
    {
        return (string)$this->getData(self::PAYMENT_DESCRIPTION);
    }

    /**
     * @return string
     */
    public function getPspReference(): string
    {
        return (string)$this->getData(self::PSP_REFERENCE);
    }

    /**
     * @return string
     */
    public function getReference(): string
    {
        return (string)$this->getData(self::REFERENCE);
    }

    /**
     * @return string
     */
    public function getSubReference(): string
    {
        return (string)$this->getData(self::SUB_REFERENCE);
    }

    /**
     * @return string
     */
    public function getUserFlow(): string
    {
        return (string)$this->getData(self::USER_FLOW);
    }

    /**
     * @return Profile
     */
    public function getProfile(): Profile
    {
        return $this->getData(self::PROFILE);
    }

    public function isCreated()
    {
        return $this->getState() === self::STATE_CREATED;
    }

    public function isAborter()
    {
        return $this->getState() === self::STATE_ABORTED;
    }

    public function isExpired()
    {
        return $this->getState() === self::STATE_EXPIRED;
    }

    public function isAuthorised()
    {
        return $this->getState() === self::STATE_AUTHORIZED;
    }

    public function isTerminated()
    {
        return $this->getState() === self::STATE_TERMINATED;
    }

    public function getRawData(): string
    {
        return (string)$this->getData('raw_data');
    }
}
