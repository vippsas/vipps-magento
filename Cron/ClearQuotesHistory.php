<?php
/**
 * Copyright 2019 Vipps
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 *    documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 *  and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED
 * TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */

namespace Vipps\Payment\Cron;

use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Intl\DateTimeFactory;
use Psr\Log\LoggerInterface;
use Vipps\Payment\{Model\Order\Cancellation\Config,
    Model\ResourceModel\Quote\Collection as VippsQuoteCollection,
    Model\ResourceModel\Quote\CollectionFactory as VippsQuoteCollectionFactory};

/**
 * Class ClearQuotesHistory
 * @package Vipps\Payment\Cron
 */
class ClearQuotesHistory
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Config
     */
    private $cancellationConfig;

    /**
     * @var VippsQuoteCollectionFactory
     */
    private $vippsQuoteCollectionFactory;

    /**
     * @var DateTimeFactory
     */
    private $dateTimeFactory;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger
     * @param Config $cancellationConfig
     * @param VippsQuoteCollectionFactory $vippsQuoteCollectionFactory
     * @param DateTimeFactory $dateTimeFactory
     */
    public function __construct(
        LoggerInterface $logger,
        Config $cancellationConfig,
        VippsQuoteCollectionFactory $vippsQuoteCollectionFactory,
        DateTimeFactory $dateTimeFactory
    ) {
        $this->logger = $logger;
        $this->cancellationConfig = $cancellationConfig;
        $this->vippsQuoteCollectionFactory = $vippsQuoteCollectionFactory;
        $this->dateTimeFactory = $dateTimeFactory;
    }

    /**
     * Clear old vipps quote history.
     */
    public function execute()
    {
        $days = $this->cancellationConfig->getQuoteStoragePeriod();
        if (!$days) {
            return;
        }

        $dateRemoveTo = $this->dateTimeFactory->create();

        try {
            $dateRemoveTo->sub(new \DateInterval("P{$days}D"));  //@codingStandardsIgnoreLine
            $dateTimeFormatted = $dateRemoveTo->format(Mysql::DATETIME_FORMAT);

            $this->logger->debug('Remove quotes information till ' . $dateTimeFormatted);

            /** @var VippsQuoteCollection $collection */
            $collection = $this->vippsQuoteCollectionFactory->create();
            $collection->addFieldToFilter('updated_at', ['lt' => $dateTimeFormatted]);
            $query = $collection
                ->getSelect()
                ->deleteFromSelect('main_table');

            $collection->getConnection()->query($query);  //@codingStandardsIgnoreLine

            $this->logger->debug('Deleted records: ' . $query);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }
    }
}
