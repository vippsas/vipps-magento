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

namespace Vipps\Payment\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\Model\QuoteFactory;
use Vipps\Payment\Model\ResourceModel\Quote as QuoteResource;

/**
 * Class QuoteRepository
 */
class QuoteRepository implements QuoteRepositoryInterface
{
    /**
     * @var QuoteResource
     */
    private $quoteResource;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * QuoteRepository constructor.
     *
     * @param QuoteResource $quoteResource .
     * @param QuoteFactory $quoteFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        QuoteResource $quoteResource,
        QuoteFactory $quoteFactory,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger
    ) {
        $this->quoteResource = $quoteResource;
        $this->quoteFactory = $quoteFactory;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * Save monitoring record
     *
     * @param QuoteInterface $quote
     * @return QuoteInterface
     * @throws CouldNotSaveException
     */
    public function save(QuoteInterface $quote): QuoteInterface
    {
        try {
            $this->quoteResource->save($quote);
            return $quote;
        } catch (\Exception $e) {
            $this->logger->error($e);
            throw new CouldNotSaveException(
                __(
                    'Could not save Vipps Quote: %1',
                    $e->getMessage()
                ),
                $e
            );
        }
    }

    /**
     * Load monitoring quote by quote.
     *
     * @param $quoteId
     *
     * @return Quote
     * @throws NoSuchEntityException
     */
    public function loadByQuote($quoteId): QuoteInterface
    {
        $vippsQuote = $this->quoteFactory->create();
        $this->quoteResource->load($vippsQuote, $quoteId, 'quote_id');

        if (!$vippsQuote->getId()) {
            throw NoSuchEntityException::singleField('quote_id', $quoteId);
        }

        return $vippsQuote;
    }

    /**
     * @param string $reservedOrderId
     *
     * @return QuoteInterface
     * @throws NoSuchEntityException
     */
    public function loadByOrderId($reservedOrderId): QuoteInterface
    {
        $vippsQuote = $this->quoteFactory->create();
        $this->quoteResource->load($vippsQuote, $reservedOrderId, 'reserved_order_id');

        if (!$vippsQuote->getId()) {
            throw NoSuchEntityException::singleField('reserved_order_id', $reservedOrderId);
        }

        return $this->tryLocateOrder($vippsQuote);
    }

    /**
     * @param int $vippsQuoteId
     *
     * @return QuoteInterface
     * @throws NoSuchEntityException
     */
    public function load(int $vippsQuoteId)
    {
        /** @var Quote $vippsQuote */
        $vippsQuote = $this->quoteFactory->create();
        $this->quoteResource->load($vippsQuote, $vippsQuoteId);

        if (!$vippsQuote->getId()) {
            throw NoSuchEntityException::singleField('entity_id', $vippsQuoteId);
        }

        return $this->tryLocateOrder($vippsQuote);
    }

    /**
     * @param QuoteInterface $vippsQuote
     *
     * @return QuoteInterface
     */
    private function tryLocateOrder(QuoteInterface $vippsQuote): ?QuoteInterface
    {
        if ($vippsQuote->getOrderId()) {
            return $vippsQuote;
        }

        $reservedOrderId = $vippsQuote->getReservedOrderId();
        $this->searchCriteriaBuilder->addFilter(OrderInterface::INCREMENT_ID, $reservedOrderId);
        $criteria = $this->searchCriteriaBuilder->create();
        $orders = $this->orderRepository->getList($criteria)->getItems();

        if (empty($orders)) {
            return $vippsQuote;
        }

        $order = current($orders);
        if ($order) {
            try {
                $vippsQuote->setOrderId((int)$order->getEntityId());
                if ($vippsQuote->getStatus() == QuoteInterface::STATUS_NEW) {
                    $vippsQuote->setStatus(QuoteInterface::STATUS_PENDING);
                }
                $vippsQuote = $this->save($vippsQuote);
            } catch (\Throwable $t) {
                $this->logger->error($t);
            }
        }

        return $vippsQuote;
    }
}
