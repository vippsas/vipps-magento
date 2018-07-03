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

use Magento\Sales\Model\{Order, ResourceModel\Order\Collection, ResourceModel\Order\CollectionFactory};
use Vipps\Payment\Api\CommandManagerInterface;

/**
 * Class FetchOrderStatus
 * @package Vipps\Payment\Cron
 */
class FetchOrderStatus
{
    /**
     * Order collection page size
     */
    const COLLECTION_PAGE_SIZE = 100;

    /**
     * Order  Status pending
     *
     * var string
     */
    const STATUS_PENDING = 'pending';

    /**
     * @var CollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * FetchOrderStatus constructor.
     *
     * @param CollectionFactory $orderCollectionFactory
     * @param CommandManagerInterface $commandManager
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        CommandManagerInterface $commandManager
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->commandManager = $commandManager;
    }

    /**
     * Go through 'not-confirmed' orders in Magento and 'cancel' them if not approved in Vipps
     */
    public function execute()
    {
        $currentPage = 1;
        do {
            /** @var Collection $orderCollection */
            $orderCollection = $this->orderCollectionFactory->create();

            $orderCollection->setPageSize(self::COLLECTION_PAGE_SIZE);
            $orderCollection->setCurPage($currentPage);
            $orderCollection->addFieldToSelect(['entity_id', '.increment_id']);
            $orderCollection->join(
                    ['sop' => $orderCollection->getTable('sales_order_payment')],
                    'main_table.entity_id = sop.parent_id',
                    ['sop.method']
                );
            $orderCollection->addFieldToFilter('sop.method', ['eq' => 'vipps']);
            $orderCollection->addFieldToFilter('main_table.status', ['in' => [self::STATUS_PENDING]]);

            /** @var Order $order */
            foreach ($orderCollection as $order) {
                $this->commandManager->getOrderStatus($order->getIncrementId());
            }

            $currentPage++;
        } while ($currentPage <= $orderCollection->getLastPageNumber());
    }
}
