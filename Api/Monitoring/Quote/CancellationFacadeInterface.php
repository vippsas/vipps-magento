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

namespace Vipps\Payment\Api\Monitoring\Quote;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\Model\Monitoring\Quote\Cancellation;

/**
 * Quote Cancellation Facade.
 * It cancels the quote. Provides an ability to send cancellation request to Vipps.
 */
interface CancellationFacadeInterface
{
    /**
     * vipps_monitoring extension attribute requires to be loaded in the quote.
     *
     * @param CartInterface $quote
     * @param string $type
     * @param string $reason
     * @param Transaction|null $transaction
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function cancelMagento(CartInterface $quote, string $type, string $reason, Transaction $transaction = null);

    /**
     * @param CartInterface $quote
     * @param Cancellation $cancellation
     * @param Transaction|null $transaction
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function cancelVipps(CartInterface $quote, Cancellation $cancellation, Transaction $transaction = null);
}
