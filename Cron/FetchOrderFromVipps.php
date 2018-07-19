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
namespace Vipps\Payment\Cron;

use Magento\Quote\Model\{ResourceModel\Quote\Collection, ResourceModel\Quote\CollectionFactory};
use Vipps\Payment\{
    Gateway\Command\PaymentDetailsProvider,
    Model\OrderPlace,
    Gateway\Transaction\TransactionBuilder
};
use Psr\Log\LoggerInterface;

/**
 * Class FetchOrderStatus
 * @package Vipps\Payment\Cron
 */
class FetchOrderFromVipps
{
    /**
     * Order collection page size
     */
    const COLLECTION_PAGE_SIZE = 100;

    /**
     * @var CollectionFactory
     */
    private $quoteCollectionFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PaymentDetailsProvider
     */
    private $paymentDetailsProvider;

    /**
     * @var OrderPlace
     */
    private $orderPlace;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * FetchOrderFromVipps constructor.
     *
     * @param CollectionFactory $quoteCollectionFactory
     * @param PaymentDetailsProvider $paymentDetailsProvider
     * @param TransactionBuilder $transactionBuilder
     * @param OrderPlace $orderManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $quoteCollectionFactory,
        PaymentDetailsProvider $paymentDetailsProvider,
        TransactionBuilder $transactionBuilder,
        OrderPlace $orderManagement,
        LoggerInterface $logger
    ) {
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->paymentDetailsProvider = $paymentDetailsProvider;
        $this->transactionBuilder = $transactionBuilder;
        $this->orderPlace = $orderManagement;
        $this->logger = $logger;
    }

    /**
     * Create orders from Vipps that are not created in Magento yet
     */
    public function execute()
    {
        $currentPage = 1;
        do {
            $quoteCollection = $this->createCollection($currentPage);
            foreach ($quoteCollection as $quote) {
                try {
                    $response = $this->paymentDetailsProvider->get(['orderId' => $quote->getReservedOrderId()]);
                    $transaction = $this->transactionBuilder->setData($response)->build();
                    $this->orderPlace->execute($quote, $transaction);
                } catch (\Exception $e) {
                    $this->logger->critical($e->getMessage());
                }
            }
            $this->logger->debug('Fetch payment details, page: ' . $currentPage);
            $currentPage++;
        } while ($currentPage <= $quoteCollection->getLastPageNumber());
    }

    /**
     * @param $currentPage
     *
     * @return Collection
     */
    private function createCollection($currentPage)
    {
        /** @var Collection $collection */
        $collection = $this->quoteCollectionFactory->create();

        $collection->setPageSize(self::COLLECTION_PAGE_SIZE);
        $collection->setCurPage($currentPage);
        $collection->addFieldToSelect(['entity_id', 'reserved_order_id', 'store_id']);
        $collection->join(
            ['p' => $collection->getTable('quote_payment')],
            'main_table.entity_id = p.quote_id',
            ['p.method']
        );
        $collection->addFieldToFilter('p.method', ['eq' => 'vipps']);
        $collection->addFieldToFilter('main_table.is_active', ['in' => ['0']]);
        $collection->addFieldToFilter('main_table.reserved_order_id', ['neq' => '']);
        return $collection;
    }
}
