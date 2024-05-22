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
namespace Vipps\Payment\Gateway\Http;

use Magento\Framework\App\ScopeResolverInterface;
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Vipps\Payment\Gateway\Http\Client\ClientInterface;
use Vipps\Payment\Model\OrderLocator;
use Vipps\Payment\Model\UrlResolver;

/**
 * Class TransferFactory
 * @package Vipps\Payment\Gateway\Http
 */
class TransferFactory implements TransferFactoryInterface
{
    /**
     * @var string
     */
    private $endpointUrl;

    /**
     * @var string
     */
    private $method;

    /**
     * @var TransferBuilder
     */
    private $transferBuilder;

    /**
     * @var UrlResolver
     */
    private $urlResolver;

    /**
     * @var array
     */
    private $urlParams = [];

    /**
     * @var array
     */
    private $allowedFields = [
        'orderId',
        'customerInfo',
        'merchantInfo',
        'transaction',
        'orderLines',
        'bottomLine',
        'shouldReleaseRemainingFunds',
        'sub'
    ];

    /**
     * @param TransferBuilder $transferBuilder
     * @param UrlResolver $urlResolver
     * @param string $method
     * @param string $endpointUrl
     * @param array $urlParams
     */
    public function __construct(
        TransferBuilder $transferBuilder,
        UrlResolver $urlResolver,
        string $method,
        string $endpointUrl,
        array $urlParams = []
    ) {
        $this->transferBuilder = $transferBuilder;
        $this->urlResolver = $urlResolver;
        $this->method = $method;
        $this->endpointUrl = $endpointUrl;
        $this->urlParams = $urlParams;
    }

    /**
     * Builds gateway transfer object
     *
     * @param array $request
     *
     * @return TransferInterface
     * @throws \Exception
     */
    public function create(array $request)
    {
        $this->transferBuilder->setHeaders([
            ClientInterface::HEADER_PARAM_X_REQUEST_ID => $request['requestId'] ?? $this->generateRequestId()
        ]);

        $scopeId = $request['scopeId'] ?? null;
        $request = $this->filterPostFields($request);

        $this->transferBuilder
            ->setBody($this->getBody($request))
            ->setMethod($this->method)
            ->setUri($this->getUrl($request))
            ->setClientConfig(['scopeId' => $scopeId]);

        return $this->transferBuilder->build();
    }

    /**
     * Remove all fields that are not marked as allowed.
     *
     * @param array $fields
     * @return array
     */
    private function filterPostFields($fields)
    {
        $allowedFields = $this->allowedFields;
        $fields = array_filter(
            $fields,
            function ($key) use ($allowedFields) {
                return in_array($key, $allowedFields);
            },
            ARRAY_FILTER_USE_KEY
        );

        return $fields;
    }

    /**
     * Generating Url.
     *
     * @param $request
     *
     * @return string
     */
    private function getUrl(array $request = [])
    {
        $endpointUrl = $this->endpointUrl;
        /** Binding url parameters if they were specified */
        foreach ($this->urlParams as $paramValue) {
            if (isset($request[$paramValue])) {
                $endpointUrl = str_replace(':' . $paramValue, $request[$paramValue], $this->endpointUrl);
            }
        }
        return $this->urlResolver->getUrl($endpointUrl);
    }

    /**
     * Method to get needed content body from request.
     *
     * @param array $request
     *
     * @return array
     */
    private function getBody(array $request = [])
    {
        foreach ($this->urlParams as $paramValue) {
            if (isset($request[$paramValue])) {
                unset($request[$paramValue]);
            }
        }

        return $request;
    }

    /**
     * Generate value of request id for current request
     *
     * @return string
     */
    private function generateRequestId()
    {
        return uniqid('req-id-', true);
    }
}
