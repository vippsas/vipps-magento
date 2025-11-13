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
namespace Vipps\Payment\Model\Profiling;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Store\Model\ScopeInterface;
use Vipps\Payment\Api\Profiling\Data\ItemInterface;
use Vipps\Payment\Api\Profiling\Data\ItemInterfaceFactory;
use Vipps\Payment\Api\Profiling\ItemRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Api\SortOrder;

use Laminas\Http\Response;
use Magento\Framework\Json\DecoderInterface;
use Vipps\Payment\Model\Gdpr\Compliance;

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
     * @var Compliance
     */
    private $gdprCompliance;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var SortOrderBuilder
     */
    private $sortOrderBuilder;

    /**
     * Profiler constructor.
     *
     * @param ScopeConfigInterface $config
     * @param ItemInterfaceFactory $dataItemFactory
     * @param ItemRepositoryInterface $itemRepository
     * @param DecoderInterface $jsonDecoder
     * @param Compliance $gdprCompliance
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        ScopeConfigInterface $config,
        ItemInterfaceFactory $dataItemFactory,
        ItemRepositoryInterface $itemRepository,
        DecoderInterface $jsonDecoder,
        Compliance $gdprCompliance,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder
    ) {
        $this->config = $config;
        $this->dataItemFactory = $dataItemFactory;
        $this->itemRepository = $itemRepository;
        $this->jsonDecoder = $jsonDecoder;
        $this->gdprCompliance = $gdprCompliance;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
    }

    /**
     * @param TransferInterface $transfer
     * @param Response $response
     *
     * @return string|null
     */
    public function save(TransferInterface $transfer, Response $response)
    {
        if (!$this->isProfilingEnabled()) {
            return null;
        }
        /** @var ItemInterface $itemDO */
        $itemDO = $this->dataItemFactory->create();

        $data = $this->parseDataFromTransferObject($transfer);

        $requestType = $data['type'] ?? 'undefined';
        $orderId = $data['order_id'] ?? $this->parseOrderId($response);

        $responseData = $this->parseResponse($response);

        if ($requestType === 'details' && $orderId) {
            $logDetails = $this->addNewPaymentDetailsRequest($orderId, $responseData);
            if (!$logDetails) {
                return null;
            }
        }

        $itemDO->setRequestType($requestType);
        $itemDO->setRequest($this->packArray(
            array_merge(['headers' => $transfer->getHeaders()], ['body' => $transfer->getBody()])
        ));

        $itemDO->setStatusCode($response->getStatusCode());
        $itemDO->setIncrementId($orderId);
        $itemDO->setResponse($this->packArray($responseData));
        $itemDO->setCreatedAt(gmdate(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT));

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
        return ($content['orderId'] ?? $content['reference'] ?? null);
    }

    /**
     * Parse data from transfer object
     *
     * @param TransferInterface $transfer
     *
     * @return array
     */
    private function parseDataFromTransferObject(TransferInterface $transfer)
    {
        $result = [];

        // Details
        if (preg_match('/payments(?:\/([A-Za-z0-9]+))?$/', $transfer->getUri(), $matches)) {
            $result['order_id'] = $matches[1] ?? ($transfer->getUrlParameters()['reference'] ?? null);
            $result['type'] = TypeInterface::GET_PAYMENT_DETAILS;
        }

        // Initiate
        if (preg_match('/payments(\/([^\/]+)\/([a-z]+))?$/', $transfer->getUri(), $matches)) {
            $result['order_id'] = $matches[2] ?? ($transfer->getBody()['transaction']['orderId'] ?? null);
            $result['type'] = $matches[3] ?? TypeInterface::INITIATE_PAYMENT;
        }

        // Send receipt
        if (preg_match('/order-management\/v2\/ecom\/receipts\/(.+)/i', $transfer->getUri(), $matches)) {
            $result['order_id'] = $matches[1] ?? null;
            $result['type'] = TypeInterface::SEND_RECEIPT;
        }

        return $result;
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
        try {
            $result = $this->jsonDecoder->decode($response->getContent());
        } catch (\Exception $e) {
            $result = [];
        }
        return $this->depersonalizedResponse($result);
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
        if (isset($response['url'])) {
            unset($response['url']);
        }

        return $this->gdprCompliance->process($response);
    }

    /**
     * Check whether profiler enabled or not
     *
     * @return bool
     */
    private function isProfilingEnabled()
    {
        return (bool)$this->config->getValue('payment/vipps/profiling', ScopeInterface::SCOPE_STORE);
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
            foreach ((array)$data as $key => $value) {
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

    private function addNewPaymentDetailsRequest($orderId, $responseData) {
        $sort = $this->sortOrderBuilder
            ->setField('created_at')
            ->setDirection(SortOrder::SORT_DESC)
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $orderId, 'eq')
            ->addFilter('request_type', 'details', 'eq')
            ->addSortOrder($sort)
            ->setPageSize(1)
            ->setCurrentPage(1)
            ->create();

        $searchResults = $this->itemRepository->getList($searchCriteria);
        $items = $searchResults->getItems();
        if (!empty($items)) {
            if (preg_match('/\bstate\s*:\s*(?:"([^"]+)"|\'([^\']+)\'|([^\s\}\n\r]+))/i', $items[0]['response'], $m)) {
                // capture group 1 or 2 or 3
                $oldState = trim($m[1] ?: $m[2] ?: $m[3]);
                if (isset($responseData['state']) && $responseData['state'] === $oldState) {
                    return false;
                }
            }
        }
        return true;
    }
}
