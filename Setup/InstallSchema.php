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
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Class InstallSchema
 * @package Vipps\Payment\Setup
 * @codeCoverageIgnore
 */
class InstallSchema implements InstallSchemaInterface //@codingStandardsIgnoreLine
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context) //@codingStandardsIgnoreLine
    {
        $installer = $setup;
        $installer->startSetup();

        /**
         * Create table 'vipps_payment_jwt'
         */
        $table = $installer->getConnection()->newTable(
            $installer->getTable('vipps_payment_jwt')
        )->addColumn(
            'token_id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'Token ID'
        )->addColumn(
            'scope_id',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '0'],
            'Config Scope Id'
        )->addColumn(
            'token_type',
            Table::TYPE_TEXT,
            16,
            ['nullable' => false, 'default' => 'Bearer'],
            'Token Type (default Bearer)'
        )->addColumn(
            'expires_in',
            Table::TYPE_INTEGER,
            null,
            [],
            'Token expiry duration in seconds'
        )->addColumn(
            'ext_expires_in',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => 0],
            'Any extra expiry time. This is zero only'
        )->addColumn(
            'expires_on',
            Table::TYPE_INTEGER,
            null,
            [],
            'Token expiry time in epoch time format'
        )->addColumn(
            'not_before',
            Table::TYPE_INTEGER,
            null,
            [],
            'Token creation time in epoch time format'
        )->addColumn(
            'resource',
            Table::TYPE_TEXT,
            255,
            [],
            'A common resource object that comes by default. Not used in token validation'
        )->addColumn(
            'access_token',
            Table::TYPE_TEXT,
            '8k',
            ['nullable' => false],
            'The actual access token that needs to be used in request header'
        )->setComment(
            'JWT access token'
        );
        $installer->getConnection()->createTable($table);

        /**
         * Create table 'vipps_profiling'
         */
        $table = $installer->getConnection()->newTable(
            $installer->getTable('vipps_profiling')
        )->addColumn(
            'entity_id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'Entity Id'
        )->addColumn(
            'increment_id',
            Table::TYPE_TEXT,
            32,
            [],
            'Increment Id'
        )->addColumn(
            'status_code',
            Table::TYPE_TEXT,
            4,
            [],
            'Status Code'
        )->addColumn(
            'request_type',
            Table::TYPE_TEXT,
            50,
            [],
            'Request Type'
        )->addColumn(
            'request',
            Table::TYPE_TEXT,
            '64k',
            [],
            'Request'
        )->addColumn(
            'response',
            Table::TYPE_TEXT,
            '64k',
            [],
            'Response'
        )->addColumn(
            'created_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Created At'
        );
        $installer->getConnection()->createTable($table);

        $installer->endSetup();
    }
}
