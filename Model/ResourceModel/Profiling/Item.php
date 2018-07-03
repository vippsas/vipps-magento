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
namespace Vipps\Payment\Model\ResourceModel\Profiling;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Item
 * @package Vipps\Payment\Model\ResourceModel\Profiling
 */
class Item extends AbstractDb
{
    /**
     * Main table name
     */
    const TABLE_NAME = 'vipps_profiling';

    /**
     * Index field name
     */
    const INDEX_FIELD = 'entity_id';

    /**
     * Initialize resource model
     */
    protected function _construct() //@codingStandardsIgnoreLine
    {
        $this->_init(self::TABLE_NAME, self::INDEX_FIELD);
    }

    /**
     * Delete entity by id
     *
     * @param $id
     */
    public function deleteById($id)
    {
        $connection = $this->getConnection();
        $connection->delete(self::TABLE_NAME, [self::INDEX_FIELD . ' = ?' => $id]);
    }
}
