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

namespace Vipps\Payment\Api\Quote;

use Magento\Quote\Api\Data\CartInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Gateway\Transaction\Transaction;

/**
 * Cancels Vipps payment everywhere.
 * @api
 */
interface CancelFacadeInterface
{
    /**
     * Cancel Vipps payment transaction both, Magento and Vipps.
     *
     * @param QuoteInterface $vippsQuote
     * @param string $type
     * @param string $reason
     * @param CartInterface|null $quote
     * @param Transaction|null $transaction
     */
    public function cancel(
        QuoteInterface $vippsQuote,
        string $type,
        string $reason,
        CartInterface $quote = null,
        Transaction $transaction = null
    );
}
