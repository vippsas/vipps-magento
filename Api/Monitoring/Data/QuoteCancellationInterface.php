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

namespace Vipps\Payment\Api\Monitoring\Data;

/**
 * Interface QuoteInterface
 * @api
 */
interface QuoteCancellationInterface
{
    /**
     * @const string
     */
    const IS_CANCELED = 'is_canceled';

    /**
     * @const string
     */
    const IS_CANCELED_YES = 1;

    /**
     * @const string
     */
    const IS_CANCELED_NO = 0;

    /**
     * @const string
     */
    const CANCEL_TYPE = 'cancel_type';

    /**
     * @const string
     */
    const CANCEL_REASON = 'cancel_reason';

    /**
     * @const string Canceled in vipps.
     */
    const CANCEL_TYPE_VIPPS = 'vipps';

    /**
     * @const string Canceled in magento.
     */
    const CANCEL_TYPE_MAGENTO = 'magento';

    /**
     * @const string Canceled everywhere.
     */
    const CANCEL_TYPE_ALL = 'all';

    /**
     * @return string|null
     */
    public function getCancelType();

    /**
     * @return string|null
     */
    public function getCancelReason();

    /**
     * @param bool $isCanceled
     * @return self
     */
    public function setIsCanceled(bool $isCanceled);

    /**
     * @param string $reason
     */
    public function setCancelReason(string $reason);

    /**
     * @param string $type
     * @return self
     */
    public function setCancelType($type);

    /**
     * @return bool
     */
    public function isCanceled();
}
