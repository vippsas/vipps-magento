<?php
/**
 * Copyright 2020 Vipps
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

namespace Vipps\Payment\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Vipps\Payment\Api\Data\QuoteInterface;

/**
 * Class Quote
 */
class Quote extends AbstractDb
{
    /**
     * Main table name
     */
    const TABLE_NAME = 'vipps_quote';

    /**
     * Index field name
     */
    const INDEX_FIELD = 'entity_id';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct() //@codingStandardsIgnoreLine
    {
        $this->_init(self::TABLE_NAME, self::INDEX_FIELD);
    }

    /**
     * @param AbstractModel $object
     * @param $value
     * @param null $field
     *
     * @return $this
     * @throws LocalizedException
     */
    public function loadNewByQuote(AbstractModel $object, $value, $field = null)
    {
        $object->beforeLoad($value, $field);
        if ($field === null) {
            $field = $this->getIdFieldName();
        }

        $connection = $this->getConnection();
        if ($connection && $value !== null) {
            $connection = $this->getConnection();
            $select = $connection->select()
                ->from($this->getMainTable())
                ->where("$field = ?", $value)
                ->where('status = ?', QuoteInterface::STATUS_NEW)
                ->limit(1);
            $data = $connection->fetchRow($select);

            if ($data) {
                $object->setData($data);
            }
        }

        $this->unserializeFields($object);
        $this->_afterLoad($object);
        $object->afterLoad();
        $object->setOrigData();
        $object->setHasDataChanges(false);

        return $this;
    }
}
