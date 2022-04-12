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

namespace Vipps\Payment\Api\Data;

/**
 * Interface QuoteInterface
 * @api
 */
interface QuoteStatusInterface
{
    /**
     * @const string(10)
     */
    const FIELD_STATUS = 'status';

    /**
     * @const string
     */
    const STATUS_NEW = 'new';

    /**
     * @const string
     */
    const STATUS_PENDING = 'pending';

    /**
     * @const string
     */
    const STATUS_CANCELED = 'canceled';

    /**
     * @const string
     */
    const STATUS_CANCEL_FAILED = 'cancel_failed';

    /**
     * @const string
     */
    const STATUS_EXPIRED = 'expired';

    /**
     * @const string
     */
    const STATUS_RESERVED = 'reserved';

    /**
     * @const string
     */
    const STATUS_RESERVE_FAILED = 'reserve_failed';

    /**
     * @const string
     */
    const STATUS_REVERTED = 'reverted';

    /**
     * @const string
     */
    const STATUS_REVERT_FAILED = 'revert_failed';

    /**
     * @return string
     */
    public function getStatus();

    /**
     * @param string $status
     * @return self
     */
    public function setStatus(string $status);
}
