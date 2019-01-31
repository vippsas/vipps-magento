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

namespace Vipps\Payment\Model\Quote;

use Magento\Quote\Api\Data\CartInterface;
use Vipps\Payment\{Api\CommandManagerInterface,
    Api\Data\QuoteInterface,
    Api\Data\QuoteStatusInterface,
    Api\Quote\CancelFacadeInterface,
    Model\QuoteRepository};

/**
 * Quote Cancellation Facade.
 * It cancels the quote. Provides an ability to send cancellation request to Vipps.
 */
class CancelFacade implements CancelFacadeInterface
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * CancellationFacade constructor.
     * @param CommandManagerInterface $commandManager
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        QuoteRepository $quoteRepository
    ) {
        $this->commandManager = $commandManager;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * vipps_monitoring extension attribute requires to be loaded in the quote.
     *
     * @param QuoteInterface $vippsQuote
     * @param CartInterface $quote
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Throwable
     */
    public function cancel(
        QuoteInterface $vippsQuote,
        CartInterface $quote
    ) {
        try {
            // cancel order on vipps side
            $this->commandManager->cancel($quote->getPayment());
            $vippsQuote->setStatus(QuoteStatusInterface::STATUS_CANCELED);
        } catch (\Throwable $exception) {
            // Log the exception
            $vippsQuote->setStatus(QuoteStatusInterface::STATUS_CANCEL_FAILED);
            throw $exception;
        } finally {
            $this->quoteRepository->save($vippsQuote);
        }
    }
}
