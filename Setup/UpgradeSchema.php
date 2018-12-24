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

use Magento\Framework\Setup\{SchemaSetupInterface, UpgradeSchemaInterface, ModuleContextInterface};
use Magento\Framework\DB\Ddl\Table;

class UpgradeSchema implements UpgradeSchemaInterface // @codingStandardsIgnoreLine
{
    /**
     * Schema changes on the module upgrade.
     *
     * @param SchemaSetupInterface $setup Setup interface.
     * @param ModuleContextInterface $context Module context.
     * @throws \Zend_Db_Exception
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context) // @codingStandardsIgnoreLine
    {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.1.0', '<')) {
            $this->addPaymentJwtScope($installer);
        }

        if (version_compare($context->getVersion(), '1.2.0', '<')) {
            $this->createVippsQuoteTable($installer);
            $this->createVippsAttemptsTable($installer);
        }

        $installer->endSetup();
    }

    /**
     * @param SchemaSetupInterface $installer
     */
    private function addPaymentJwtScope(SchemaSetupInterface $installer)
    {
        $tableName = $installer->getTable('vipps_payment_jwt');
        $installer->getConnection()->addColumn(
            $tableName,
            'scope',
            [
                'type' => Table::TYPE_TEXT,
                'length' => 8,
                'after' => 'token_id',
                'nullable' => false,
                'default' => 'default',
                'comment' => 'Scope'
            ]
        );
        $installer->getConnection()->truncateTable($tableName);
    }

    /**
     * Install Vipps quote monitoring table.
     *
     * @param SchemaSetupInterface $installer
     * @throws \Zend_Db_Exception
     */
    private function createVippsQuoteTable(SchemaSetupInterface $installer): void
    {
        $connection = $installer->getConnection();

        $table = $connection->newTable($connection->getTableName('vipps_quote'))
            ->addColumn(
                'entity_id',
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Entity Id'
            )->addColumn(
                'quote_id',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true],
                'Quote Id'
            )->addColumn(
                'reserved_order_id',
                Table::TYPE_TEXT,
                32,
                ['nullable' => false, 'default' => ''],
                'Order Increment Id'
            )->addColumn(
                'created_at',
                Table::TYPE_DATETIME,
                null,
                [],
                'Created at'
            )->addColumn(
                'updated_at',
                Table::TYPE_DATETIME,
                null,
                [],
                'Updated at');

        $installer->getConnection()->createTable($table);
    }

    /**
     * Install Quote submitting attempts table.
     *
     * @param SchemaSetupInterface $installer Schema installer.
     * @throws \Zend_Db_Exception
     */
    private function createVippsAttemptsTable(SchemaSetupInterface $installer): void
    {
        $connection = $installer->getConnection();

        $table = $connection->newTable($connection->getTableName('vipps_quote_attempt'))
            ->addColumn(
                'entity_id',
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Entity Id'
            )->addColumn(
                'parent_id',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true],
                'Vipps Quote Id'
            )->addColumn(
                'message',
                Table::TYPE_TEXT,
                null,
                [],
                'Message'
            )->addColumn(
                'created_at',
                Table::TYPE_DATETIME,
                null,
                [],
                'Created at'
            )
            ->addIndex($installer->getIdxName('vipps_quote_attempts', 'parent_id'), 'parent_id')
            ->addForeignKey(
                $installer->getFkName('vipps_quote_attempts', 'parent_id', 'vipps_quote', 'entity_id'),
                'parent_id',
                'vipps_quote',
                'entity_id',
                Table::ACTION_CASCADE
            );

        $installer->getConnection()->createTable($table);
    }
}
