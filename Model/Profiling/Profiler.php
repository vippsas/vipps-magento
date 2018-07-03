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
namespace Vipps\Payment\Model\Profiling;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Vipps\Payment\Api\Profiling\Data\ItemInterface;
use Vipps\Payment\Api\Profiling\Data\ItemInterfaceFactory;
use Vipps\Payment\Api\Profiling\ItemRepositoryInterface;
use Zend\Http\Response;
use Magento\Framework\Json\DecoderInterface;

class Profiler implements ProfilerInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * @var ItemInterfaceFactory
     */
    private $dataItemFactory;

    /**
     * @var ItemRepositoryInterface
     */
    private $itemRepository;

    /**
     * @var DecoderInterface
     */
    private $jsonDecoder;

    /**
     * Profiler constructor.
     *
     * @param ScopeConfigInterface $config
     * @param ItemInterfaceFactory $dataItemFactory
     * @param ItemRepositoryInterface $itemRepository
     * @param DecoderInterface $jsonDecoder
     */
    public function __construct(
        ScopeConfigInterface $config,
        ItemInterfaceFactory $dataItemFactory,
        ItemRepositoryInterface $itemRepository,
        DecoderInterface $jsonDecoder
    ) {
        $this->config = $config;
        $this->dataItemFactory = $dataItemFactory;
        $this->itemRepository = $itemRepository;
        $this->jsonDecoder = $jsonDecoder;
    }

    /**
     * @param TransferInterface $transfer
     * @param Response $response
     *
     * @return bool
     */
    public function save(TransferInterface $transfer, Response $response)
    {
        if (!$this->isProfilingEnabled()) {
            return null;
        }
        /** @var ItemInterface $itemDO */
        $itemDO = $this->dataItemFactory->create();

        $itemDO->setRequestType($this->parseRequestType($transfer));
        $itemDO->setRequest($this->packArray(
            array_merge(['headers' => $transfer->getHeaders()], ['body' => $transfer->getBody()])
        ));

        $itemDO->setStatusCode($response->getStatusCode());
        $itemDO->setIncrementId($this->parseOrderId($response));
        $itemDO->setResponse($this->packArray($this->parseResponse($response)));

        $item = $this->itemRepository->save($itemDO);
        return $item->getEntityId();
    }

    /**
     * Parse order id from response object
     *
     * @param Response $response
     *
     * @return string|null
     */
    private function parseOrderId(Response $response)
    {
        $content = $this->jsonDecoder->decode($response->getContent());
        return $content['orderId'] ?? null;
    }

    /**
     * Parse request type from url
     *
     * @param TransferInterface $transfer
     *
     * @return string
     */
    private function parseRequestType(TransferInterface $transfer)
    {
        if (preg_match('/payments(\/([^\/]+)\/([a-z]+))?$/', $transfer->getUri(), $matches)) {
            $status = $matches[3] ?? TypeInterface::INITIATE_PAYMENT;
            return $status;
        }
        return 'undefined';
    }

    /**
     * Parse response data for profiler from response
     *
     * @param Response $response
     *
     * @return array
     */
    private function parseResponse(Response $response)
    {
        return $this->depersonalizedResponse($this->jsonDecoder->decode($response->getContent()));
    }

    /**
     * Depersonalize response
     *
     * @param array $response
     *
     * @return array
     */
    private function depersonalizedResponse($response)
    {
        unset($response['url']);
        return $response;
    }

    /**
     * Check whether profiler enabled or not
     *
     * @return bool
     */
    private function isProfilingEnabled()
    {
        return (bool)$this->config->getValue('payment/vipps/profiling');
    }

    /**
     * @param $data
     *
     * @return string
     */
    private function packArray($data)
    {
        $recursive = function ($data, $indent = '') use (&$recursive) {
            $output = '{' . PHP_EOL;
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $output .= $indent . '    ' . $key . ': ' . $recursive($value, $indent . '    ') . PHP_EOL;
                } elseif (!is_object($value)) {
                    $output .= $indent . '    ' . $key . ': ' . ($value ? $value : '""') . PHP_EOL;
                }
            }
            $output .= $indent . '}' . PHP_EOL;
            return $output;
        };

        $output = $recursive($data);
        return $output;
    }
}
