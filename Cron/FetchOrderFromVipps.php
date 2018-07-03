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

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\{Quote, ResourceModel\Quote\Collection, ResourceModel\Quote\CollectionFactory};
use Vipps\Payment\{
    Gateway\Command\PaymentDetailsProvider, Model\OrderManagement,
    Gateway\Transaction\Transaction, Gateway\Transaction\TransactionBuilder
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
     * @var OrderManagement
     */
    private $orderManagement;

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
     * @param OrderManagement $orderManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $quoteCollectionFactory,
        PaymentDetailsProvider $paymentDetailsProvider,
        TransactionBuilder $transactionBuilder,
        OrderManagement $orderManagement,
        LoggerInterface $logger
    ) {
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->logger = $logger;
        $this->paymentDetailsProvider = $paymentDetailsProvider;
        $this->transactionBuilder = $transactionBuilder;
        $this->orderManagement = $orderManagement;
    }

    /**
     * Create orders from Vipps that are not created in Magento yet
     */
    public function execute()
    {
        $currentPage = 1;
        do {
            /** @var Collection $quoteCollection */
            $quoteCollection = $this->quoteCollectionFactory->create();

            $quoteCollection->setPageSize(self::COLLECTION_PAGE_SIZE);
            $quoteCollection->setCurPage($currentPage);
            $quoteCollection->addFieldToSelect(['entity_id', 'reserved_order_id']);
            $quoteCollection->join(
                    ['p' => $quoteCollection->getTable('quote_payment')],
                    'main_table.entity_id = p.quote_id',
                    ['p.method']
                );
            $quoteCollection->addFieldToFilter('p.method', ['eq' => 'vipps']);
            $quoteCollection->addFieldToFilter('main_table.is_active', ['in' => ['0']]);
            $quoteCollection->addFieldToFilter('main_table.reserved_order_id', ['neq' => '']);

            /** @var Quote $order */
            foreach ($quoteCollection as $quote) {
                try {
                    $response = $this->paymentDetailsProvider->get(['orderId' => $quote->getReservedOrderId()]);
                    if (!$response) {
                        throw new LocalizedException(__('An error occurred during order creation.'));
                    }
                    $transaction = $this->transactionBuilder->setData($response)->build();
                    $orderStatus = $transaction->getStatus();
                    if (in_array(
                        $orderStatus,
                        [Transaction::TRANSACTION_OPERATION_CANCEL, Transaction::TRANSACTION_STATUS_AUTOCANCEL]
                    )) {
                        throw new LocalizedException(__('Your order was canceled in Vipps.'));
                    }
                    $this->orderManagement->place($quote->getReservedOrderId(), $transaction);
                } catch (\Exception $e) {
                    $this->logger->critical($e->getMessage());
                }
            }
            $this->logger->debug('Processing Order From Vipps, page: ' . $currentPage);
            $currentPage++;
        } while ($currentPage <= $quoteCollection->getLastPageNumber());
    }
}
