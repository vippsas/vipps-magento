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
namespace Vipps\Payment\GatewayEpayment\Data;

/**
 * Class InitSessionBuilder
 * @package Vipps\Payment\GatewayEpayment\Data
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class InitSessionBuilder
{
    /**
     * @var InitSessionFactory
     */
    private $initSessionFactory;

    /**
     * @var array
     */
    private $response;

    /**
     * InitSessionBuilder constructor.
     *
     * @param InitSessionFactory $initSessionFactory
     */
    public function __construct(
        InitSessionFactory $initSessionFactory
    ) {
        $this->initSessionFactory = $initSessionFactory;
    }

    /**
     * Set request to builder
     *
     * @param $response
     *
     * @return $this
     */
    public function setData($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * build session object
     *
     * @return InitSession
     */
    public function build()
    {
        return $this->initSessionFactory->create(['data' => [
            'token' => $this->response['token'] ?? null,
            'checkoutFrontendUrl' => $this->response['checkoutFrontendUrl'] ?? null,
            'pollingUrl' => $this->response['pollingUrl'] ?? null,
        ]]);
    }
}
