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

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\{ModuleContextInterface, SchemaSetupInterface, UpgradeSchemaInterface};

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

        if (version_compare($context->getVersion(), '1.2.1', '<')) {
            $this->addCancelationToQuote($installer);
        }

        if (version_compare($context->getVersion(), '1.3.0', '<')) {
            $this->addCancelToVippsQuote($installer);
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
                'type'     => Table::TYPE_TEXT,
                'length'   => 8,
                'after'    => 'token_id',
                'nullable' => false,
                'default'  => 'default',
                'comment'  => 'Scope'
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
                ['nullable' => true, 'unsigned' => true],
                'Quote Id'
            )->addColumn(
                'reserved_order_id',
                Table::TYPE_TEXT,
                32,
                ['nullable' => false, 'default' => ''],
                'Order Increment Id'
            )->addColumn(
                'attempts',
                Table::TYPE_INTEGER,
                3,
                ['nullable' => false, 'default' => '0'],
                'Attempts Number'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                [Table::OPTION_DEFAULT => Table::TIMESTAMP_INIT, Table::OPTION_NULLABLE => false],
                'Created at'
            )->addColumn(
                'updated_at',
                Table::TYPE_TIMESTAMP,
                null,
                [Table::OPTION_DEFAULT => Table::TIMESTAMP_INIT_UPDATE, Table::OPTION_NULLABLE => false],
                'Updated at'
            )
            ->addIndex($installer->getIdxName('vipps_quote', 'quote_id'), 'quote_id')
            ->addForeignKey(
                $installer->getFkName('vipps_quote', 'quote_id', 'quote', 'entity_id'),
                'quote_id',
                'quote',
                'entity_id',
                Table::ACTION_SET_NULL
            );

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
                Table::TYPE_TIMESTAMP,
                null,
                [Table::OPTION_NULLABLE => false, Table::OPTION_DEFAULT => Table::TIMESTAMP_INIT],
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

    /**
     * Create cancellation table.
     *
     * @param SchemaSetupInterface $installer
     * @throws \Zend_Db_Exception
     */
    private function addCancelationToQuote(SchemaSetupInterface $installer): void
    {
        $connection = $installer->getConnection();
        $tableName = $connection->getTableName('vipps_quote');

        $connection
            ->addColumn(
                $tableName,
                'is_canceled',
                [
                    Table::OPTION_TYPE     => Table::TYPE_BOOLEAN,
                    Table::OPTION_NULLABLE => true,
                    Table::OPTION_DEFAULT  => 0,
                    'comment'              => 'Is canceled',
                    'after'                => 'attempts'
                ]
            );

        $connection
            ->addColumn(
                $tableName,
                'cancel_type',
                [
                    Table::OPTION_TYPE     => Table::TYPE_TEXT,
                    Table::OPTION_LENGTH   => 10,
                    Table::OPTION_NULLABLE => true,
                    'comment'              => 'Cancellation Type',
                    'after'                => 'is_canceled'
                ]
            );

        $connection
            ->addColumn(
                $tableName,
                'cancel_reason',
                [
                    Table::OPTION_TYPE     => Table::TYPE_TEXT,
                    Table::OPTION_NULLABLE => true,
                    'comment'              => 'Cancellation Reason',
                    'after'                => 'cancel_type'
                ]
            );
    }

    /**
     * @param SchemaSetupInterface $installer
     */
    private function addCancelToVippsQuote(SchemaSetupInterface $installer)
    {
        $connection = $installer->getConnection();

        $connection
            ->addColumn(
                $connection->getTableName('vipps_quote'),
                'is_canceled',
                [
                    Table::OPTION_TYPE     => Table::TYPE_BOOLEAN,
                    Table::OPTION_DEFAULT  => 0,
                    Table::OPTION_NULLABLE => false,
                    'comment'              => 'Is Canceled',
                    'after'                => 'reserved_order_id'
                ]
            );
    }
}
