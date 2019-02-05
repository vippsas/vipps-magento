<?php
/**
 * Copyright 2018 Vipps
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 *  documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 *  the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 *  and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED
 *  TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL
 *  THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 *  CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 *  IN THE SOFTWARE.
 *
 */

namespace Vipps\Payment\Api\Data;

use Vipps\Payment\Model\Quote;

/**
 * Interface QuoteInterface
 * @api
 */
interface QuoteInterface extends QuoteStatusInterface
{
    /**
     * @const string
     */
    const ENTITY_ID = 'entity_id';

    /**
     * @const string
     */
    const QUOTE_ID = 'quote_id';

    /**
     * @const string
     */
    const RESERVED_ORDER_ID = 'reserved_order_id';

    /**
     * @const string
     */
    const CREATED_AT = 'created_at';

    /**
     * @const string
     */
    const UPDATED_AT = 'updated_at';

    /**
     * @const string
     */
    const ATTEMPTS = 'attempts';

    /**
     * @const string
     */
    const STORE_ID = 'store_id';

    /**
     * @param int $quoteId
     * @return self
     */
    public function setQuoteId(int $quoteId);

    /**
     * @param string|null $reservedOrderId
     * @return self
     */
    public function setReservedOrderId($reservedOrderId);

    /**
     * @param string $createdAt
     * @return self
     */
    public function setCreatedAt(string $createdAt);

    /**
     * @param string $updatedAt
     * @return self
     */
    public function setUpdatedAt(string $updatedAt);

    /**
     * @param int $attempts
     * @return self
     */
    public function setAttempts(int $attempts);

    /**
     * @return int
     */
    public function getQuoteId();

    /**
     * @return string
     */
    public function getReservedOrderId();

    /**
     * @return string
     */
    public function getCreatedAt();

    /**
     * @return string
     */
    public function getUpdatedAt();

    /**
     * @return int
     */
    public function getAttempts();

    /**
     * @return int
     */
    public function getEntityId();

    /**
     * @return self
     */
    public function getStoreId();

    /**
     * @param int $storeId
     * @return self
     */
    public function setStoreId(int $storeId);
}
