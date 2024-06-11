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
namespace Vipps\Payment\GatewayEpayment\Http;

/**
 * Interface TransferInterface
 * @package Vipps\Payment\GatewayEpayment\Http
 * @api
 * @since 100.0.2
 */
interface TransferInterface extends \Magento\Payment\Gateway\Http\TransferInterface
{
    /**
     * Returns gateway client configuration
     *
     * @return array
     */
    public function getClientConfig();

    /**
     * Returns method used to place request
     *
     * @return string|int
     */
    public function getMethod();

    /**
     * Returns headers
     *
     * @return array
     */
    public function getHeaders();

    /**
     * Whether body should be encoded before place
     *
     * @return bool
     */
    public function shouldEncode();

    /**
     * Returns request body
     *
     * @return array|string
     */
    public function getBody();

    /**
     * Returns URI
     *
     * @return string
     */
    public function getUri();

    /**
     * Returns Auth username
     *
     * @return string
     */
    public function getAuthUsername();

    /**
     * Returns Auth password
     *
     * @return string
     */
    public function getAuthPassword();

    /**
     * @param string|null $name
     *
     * @return mixed
     */
    public function getUrlParameters($name = null);
}
