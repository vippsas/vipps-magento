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

namespace Vipps\Payment\Setup;

use Magento\Framework\Setup\{ModuleContextInterface, ModuleDataSetupInterface, UpgradeDataInterface};
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory;

/**
 * Class UpgradeData
 */
class UpgradeData implements UpgradeDataInterface // @codingStandardsIgnoreLine
{
    /**
     * @var CollectionFactory
     */
    private $quoteCollectionFactory;

    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->quoteCollectionFactory = $collectionFactory;
    }

    /**
     * Data updates on the module upgrade.
     *
     * @param ModuleDataSetupInterface $setup Setup interface.
     * @param ModuleContextInterface $context Module context.
     * @throws \Zend_Db_Exception
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context) // @codingStandardsIgnoreLine
    {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.2.0', '<')) {
            $this->fillVippsQuotes($setup);
        }

        $installer->endSetup();
    }

    /**
     * Fill Vipps quote tables for currently unprocessed quotes.
     *
     * @param ModuleDataSetupInterface $installer
     * @return void
     */
    private function fillVippsQuotes(ModuleDataSetupInterface $installer)
    {
        $connection = $installer->getConnection();
        $tableName = $installer->getTable('vipps_quote');

        /** @var Collection $collection */
        $collection = $this->quoteCollectionFactory->create();

        $collection
            ->addFieldToSelect('entity_id', 'quote_id')
            ->addFieldToSelect('reserved_order_id')
            ->join( //@codingStandardsIgnoreLine
                ['p' => $collection->getTable('quote_payment')], //@codingStandardsIgnoreLine
                'main_table.entity_id = p.quote_id and p.method = "vipps"',
                []
            )
            // Taking all old-style quotes that were valid for processing.
            // Adding Vipps quote monitoring records for them.
            ->addFieldToFilter('main_table.is_active', ['in' => ['0']])
            ->addFieldToFilter('main_table.updated_at', ['to' => date("Y-m-d H:i:s", time() - 300)])// 5 min
            ->addFieldToFilter('main_table.reserved_order_id', ['neq' => '']);

        $updateSql = $connection
            ->insertFromSelect( //@codingStandardsIgnoreLine
                $collection->getSelect(),
                $tableName,
                ['quote_id', 'reserved_order_id']
            );

        $connection->query($updateSql); //@codingStandardsIgnoreLine
    }
}
