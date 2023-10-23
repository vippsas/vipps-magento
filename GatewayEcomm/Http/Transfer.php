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
namespace Vipps\Payment\GatewayEcomm\Http;

/**
 * Class Transfer
 */
class Transfer implements TransferInterface
{
    /**
     * Name of Auth username field
     */
    const AUTH_USERNAME = 'username';

    /**
     * Name of Auth password field
     */
    const AUTH_PASSWORD = 'password';

    /**
     * @var array
     */
    private $clientConfig;

    /**
     * @var array
     */
    private $headers;

    /**
     * @var string
     */
    private $method;

    /**
     * @var array|string
     */
    private $body;

    /**
     * @var string
     */
    private $uri;

    /**
     * @var bool
     */
    private $encode;

    /**
     * @var array
     */
    private $auth;
    /**
     * @var array
     */
    private $urlParameters = [];

    /**
     * Transfer constructor.
     *
     * @param array $clientConfig
     * @param array $headers
     * @param $body
     * @param array $auth
     * @param $method
     * @param $uri
     * @param $encode
     * @param $urlParameters
     */
    public function __construct(
        array $clientConfig,
        array $headers,
        $body,
        array $auth,
        $method,
        $uri,
        $encode,
        $urlParameters
    ) {
        $this->clientConfig = $clientConfig;
        $this->headers = $headers;
        $this->body = $body;
        $this->auth = $auth;
        $this->method = $method;
        $this->uri = $uri;
        $this->encode = $encode;
        $this->urlParameters = $urlParameters;
    }

    /**
     * Returns gateway client configuration
     *
     * @return array
     */
    public function getClientConfig()
    {
        return $this->clientConfig;
    }

    /**
     * Returns method used to place request
     *
     * @return string|int
     */
    public function getMethod()
    {
        return (string)$this->method;
    }

    /**
     * Returns headers
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Returns request body
     *
     * @return array|string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Returns URI
     *
     * @return string
     */
    public function getUri()
    {
        return (string)$this->uri;
    }

    /**
     * @return boolean
     */
    public function shouldEncode()
    {
        return $this->encode;
    }

    /**
     * Returns Auth username
     *
     * @return string
     */
    public function getAuthUsername()
    {
        return $this->auth[self::AUTH_USERNAME];
    }

    /**
     * Returns Auth password
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->auth[self::AUTH_PASSWORD];
    }

    public function getUrlParameters($name = null)
    {
        if ($name !== null) {
            return $this->urlParameters[$name] ?? null;
        }

        return $this->urlParameters;
    }
}
