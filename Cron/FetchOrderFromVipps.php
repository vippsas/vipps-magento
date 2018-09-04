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

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\{CouldNotSaveException, NoSuchEntityException, AlreadyExistsException, InputException};
use Magento\Quote\Api\{CartRepositoryInterface, Data\CartInterface};
use Magento\Quote\Model\{ResourceModel\Quote\Collection, ResourceModel\Quote\CollectionFactory};
use Magento\Sales\Api\Data\OrderInterface;
use Vipps\Payment\{
    Api\CommandManagerInterface,
    Gateway\Exception\MerchantException,
    Gateway\Exception\VippsException,
    Gateway\Transaction\Transaction,
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
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * @var OrderPlace
     */
    private $orderPlace;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * FetchOrderFromVipps constructor.
     *
     * @param CollectionFactory $quoteCollectionFactory
     * @param CommandManagerInterface $commandManager
     * @param TransactionBuilder $transactionBuilder
     * @param OrderPlace $orderManagement
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CollectionFactory $quoteCollectionFactory,
        CommandManagerInterface $commandManager,
        TransactionBuilder $transactionBuilder,
        OrderPlace $orderManagement,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->commandManager = $commandManager;
        $this->transactionBuilder = $transactionBuilder;
        $this->orderPlace = $orderManagement;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * Create orders from Vipps that are not created in Magento yet
     *
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        try {
            $currentStore = $this->storeManager->getStore()->getId();
            $currentPage = 1;
            do {
                $quoteCollection = $this->createCollection($currentPage);
                $this->logger->debug(sprintf(
                    'Fetched payment details, page: "%s", quotes: "%s"',
                    $currentPage,
                    $quoteCollection->count() //@codingStandardsIgnoreLine
                ));
                foreach ($quoteCollection as $quote) {
                    try {
                        $this->storeManager->setCurrentStore($quote->getStore()->getId());
                        $response = $this->commandManager->getOrderStatus($quote->getReservedOrderId());
                        $transaction = $this->transactionBuilder->setData($response)->build();
                        $this->placeOrder($quote, $transaction);
                    } catch (MerchantException $e) {
                        //@todo workaround for vipps issue with order cancellation (delete this condition after fix) //@codingStandardsIgnoreLine
                        if ($e->getCode() == MerchantException::ERROR_CODE_REQUESTED_ORDER_NOT_FOUND) {
                            $this->cancelQuote($quote);
                        } else {
                            $this->logger->critical($e->getMessage());
                        }
                    } catch (\Throwable $e) {
                        $this->logger->critical($e->getMessage() . ', quote id = ' . $quote->getId());
                    } finally {
                        usleep(1000000); //delay for 1 second
                    }
                }
                $currentPage++;
            } while ($currentPage <= $quoteCollection->getLastPageNumber());
        } finally {
            $this->storeManager->setCurrentStore($currentStore);
        }
    }

    /**
     * @param CartInterface $quote
     * @param Transaction $transaction
     *
     * @return OrderInterface|null
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws VippsException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    private function placeOrder(CartInterface $quote, Transaction $transaction)
    {
        if ($transaction->isTransactionAborted()) {
            $this->cancelQuote($quote);
            return null;
        }

        $order = $this->orderPlace->execute($quote, $transaction);
        if (!$order) {
            $this->logger->critical(sprintf(
                'Order has not been placed, quote id: "%s", reserved_order_id: "%s"',
                $quote->getId(),
                $quote->getReservedOrderId()
            ));
        } else {
            $this->logger->debug(sprintf('Order placed: "%s"', $order->getIncrementId()));
        }
        return $order;
    }

    /**
     * Cancel quote by setting reserved_order_id to null
     *
     * @param CartInterface $quote
     */
    private function cancelQuote(CartInterface $quote)
    {
        $reservedOrderId = $quote->getReservedOrderId();

        $quote->setReservedOrderId(null);
        $this->cartRepository->save($quote);

        $this->logger->debug(sprintf(
            'Quote was canceled, id: "%s", reserved_order_id: "%s"',
            $quote->getId(),
            $reservedOrderId
        ));
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
        $collection->addFieldToSelect(['entity_id', 'reserved_order_id', 'store_id']); //@codingStandardsIgnoreLine
        $collection->join(
            ['p' => $collection->getTable('quote_payment')],
            'main_table.entity_id = p.quote_id',
            ['p.method']
        );
        $collection->addFieldToFilter('p.method', ['eq' => 'vipps']);
        $collection->addFieldToFilter('main_table.is_active', ['in' => ['0']]);
        $collection->addFieldToFilter('main_table.updated_at', ['to' => date("Y-m-d H:i:s", time() - 1800)]);
        $collection->addFieldToFilter('main_table.reserved_order_id', ['neq' => '']);
        return $collection;
    }
}
