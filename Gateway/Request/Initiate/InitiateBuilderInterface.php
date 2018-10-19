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

/**
 * Interface InitiateBuilderInterface
 * @package Vipps\Payment\Gateway\Request\Initiate
 */
interface InitiateBuilderInterface extends BuilderInterface
{
    /**
     * Merchant auth token identifier
     *
     * @var string
     */
    const MERCHANT_AUTH_TOKEN = 'merchant_auth_token';

    /**
     * Auth token for accessing to fallback controller
     *
     * @var string
     */
    const FALLBACK_AUTH_TOKEN = 'fallback_auth_token';

    /**
     * @var string
     */
    const PAYMENT_TYPE_EXPRESS_CHECKOUT = "eComm Express Payment";

    /**
     * @var string
     */
    const PAYMENT_TYPE_REGULAR_PAYMENT = "eComm Regular Payment";

    /**
     * This parameter will identify difference between ecomm payment and ecomm express payment.
     *
     * @var string
     */
    const PAYMENT_TYPE_KEY = 'paymentType';
}
