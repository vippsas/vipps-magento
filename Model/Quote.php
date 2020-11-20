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

namespace Vipps\Payment\Model;

use Magento\Framework\Model\AbstractModel;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Model\ResourceModel\Quote as QuoteResource;

/**
 * Quote monitoring model.
 */
class Quote extends AbstractModel implements QuoteInterface
{
    /**
     * @param int $quoteId
     * @return self
     */
    public function setQuoteId(int $quoteId)
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @param string|null $reservedOrderId Null for backward compatibility.
     * @return self
     */
    public function setReservedOrderId($reservedOrderId = '')
    {
        return $this->setData(self::RESERVED_ORDER_ID, $reservedOrderId);
    }

    /**
     * @return int
     */
    public function getQuoteId()
    {
        return $this->getData(self::QUOTE_ID);
    }

    /**
     * @return string
     */
    public function getReservedOrderId()
    {
        return $this->getData(self::RESERVED_ORDER_ID);
    }

    /**
     * @param string $createdAt
     * @return self
     */
    public function setCreatedAt(string $createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @param string $updatedAt
     * @return self
     */
    public function setUpdatedAt(string $updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @return string
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @return int
     */
    public function getEntityId()
    {
        return $this->getData(self::ENTITY_ID);
    }

    /**
     * Increment attempts.
     *
     * @return Quote
     */
    public function incrementAttempt()
    {
        return $this->setAttempts($this->getAttempts() + 1);
    }

    /**
     * @param int $attempts
     * @return self
     */
    public function setAttempts(int $attempts)
    {
        return $this->setData(self::ATTEMPTS, $attempts);
    }

    /**
     * @return int
     */
    public function getAttempts()
    {
        return $this->getData(self::ATTEMPTS);
    }

    /**
     * Clear attempts.
     * @return Quote
     */
    public function clearAttempts()
    {
        return $this->setAttempts(0);
    }

    /**
     * @param string $status
     * @return Quote
     */
    public function setStatus(string $status)
    {
        return $this->setData(self::FIELD_STATUS, $status);
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->getData(self::FIELD_STATUS);
    }

    /**
     * @param int $orderId
     * @return self
     */
    public function setOrderId(int $orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @return int
     */
    public function getOrderId()
    {
        return $this->getData(self::ORDER_ID);
    }

    /**
     * @param int $storeId
     * @return self
     */
    public function setStoreId(int $storeId)
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @return int
     */
    public function getStoreId()
    {
        return $this->getData(self::STORE_ID);
    }

    /**
     * @param string $value
     * @return self
     */
    public function setAuthToken(string $value)
    {
        return $this->setData(self::AUTH_TOKEN, $value);
    }

    /**
     * @return string
     */
    public function getAuthToken()
    {
        return $this->getData(self::AUTH_TOKEN);
    }

    /**
     * Constructor.
     */
    protected function _construct() //@codingStandardsIgnoreLine
    {
        $this->_init(QuoteResource::class);
    }
}
