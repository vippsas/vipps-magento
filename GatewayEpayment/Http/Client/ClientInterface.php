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
namespace Vipps\Payment\GatewayEpayment\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface as MagentoClientInterface;

/**
 * Interface ClientInterface
 * @package Vipps\Payment\GatewayEpayment\Http\Client
 */
interface ClientInterface extends MagentoClientInterface
{
    /**
     * @var string
     */
    const HEADER_PARAM_CONTENT_TYPE = 'Content-Type';

    /**
     * @var string
     */
    const HEADER_PARAM_AUTHORIZATION = 'Authorization';

    /**
     * @var string
     */
    const HEADER_PARAM_IDEMPOTENCY_KEY = 'Idempotency-Key';

    /**
     * @var string
     */
    const HEADER_PARAM_X_SOURCE_ADDRESS = 'X-Source-Address';

    /**
     * @var string
     */
    const HEADER_PARAM_X_TIMESTAMP = 'X-TimeStamp';

    /**
     * @var string
     */
    const HEADER_PARAM_SUBSCRIPTION_KEY = 'Ocp-Apim-Subscription-Key';

    /**
     * @var string
     */
    const HEADER_PARAM_CLIENT_ID = 'Client_Id';

    /**
     * @var string
     */
    const HEADER_PARAM_CLIENT_SECRET = 'Client_Secret';

    /**
     * @var string
     */
    const HEADER_PARAM_MERCHANT_SERIAL_NUMBER = 'Merchant-Serial-Number';
}
