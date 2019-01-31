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
    Api\Data\QuoteCancellationInterface,
    Api\Data\QuoteInterface,
    Api\Quote\CancelFacadeInterface,
    Gateway\Transaction\Transaction,
    Model\Order\Cancellation\Config,
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
     * @var Config
     */
    private $config;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var bool
     */
    private $forceVippsCancel = false;

    /**
     * CancellationFacade constructor.
     * @param CommandManagerInterface $commandManager
     * @param QuoteRepository $quoteRepository
     * @param Config $config
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        QuoteRepository $quoteRepository,
        Config $config
    ) {
        $this->commandManager = $commandManager;
        $this->config = $config;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * vipps_monitoring extension attribute requires to be loaded in the quote.
     *
     * @param CartInterface $quote
     * @param QuoteInterface $vippsQuote
     * @param string $type
     * @param string $reason
     * @param Transaction|null $transaction Order status transaction.
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function cancel(
        QuoteInterface $vippsQuote,
        string $type,
        string $reason,
        CartInterface $quote = null,
        Transaction $transaction = null
    ) {
        if ($this->isAllowedToCancelMagento($vippsQuote)) {
            $this->cancelMagento($vippsQuote, $type, $reason);
        }

        if ($this->isAllowedToCancelVipps($type, $quote, $transaction)) {
            $this->cancelVipps(QuoteCancellationInterface::CANCEL_TYPE_ALL, $quote, $vippsQuote);
        }
    }

    /**
     * @param QuoteInterface $vippsQuote
     * @return bool
     */
    private function isAllowedToCancelMagento(QuoteInterface $vippsQuote)
    {
        return !$vippsQuote->isCanceled();
    }

    /**
     * vipps_monitoring extension attribute requires to be loaded in the quote.
     *
     * @param QuoteInterface $vippsQuote
     * @param string $type
     * @param string $reason
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    private function cancelMagento(QuoteInterface $vippsQuote, string $type, string $reason)
    {
        $vippsQuote
            ->setIsCanceled(QuoteInterface::IS_CANCELED_YES)
            ->setCancelType($type)
            ->setCancelReason($reason);

        $this->quoteRepository->save($vippsQuote);
    }

    /**
     * @param $type
     * @param CartInterface|null $quote
     * @param Transaction|null $transaction
     * @return bool
     */
    private function isAllowedToCancelVipps($type, CartInterface $quote = null, Transaction $transaction = null)
    {
        return $type === QuoteCancellationInterface::CANCEL_TYPE_MAGENTO // Initiated by Magento
            && $quote // Quote is required for Vipps cancellation
            && $this->isAutomaticVippsCancellation($quote->getStoreId()) // Automatic cancel is allowed by configuration
            && $transaction // There is transaction in Vipps that can be canceled
            && $transaction->isTransactionReserved();
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    private function isAutomaticVippsCancellation($storeId = null)
    {
        return $this->isForceVippsCancel() || $this->config->isTypeAutomatic($storeId);
    }

    /**
     * @return bool
     */
    public function isForceVippsCancel(): bool
    {
        return $this->forceVippsCancel;
    }

    /**
     * @param CartInterface $quote
     * @param QuoteInterface $vippsQuote
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    private function cancelVipps(
        $type,
        CartInterface $quote,
        QuoteInterface $vippsQuote
    ) {
        // cancel order on vipps side
        $this->commandManager->cancel($quote->getPayment());

        $vippsQuote->setCancelType($type);
        $this->quoteRepository->save($vippsQuote);
    }

    /**
     * @param bool $automaticVippsCancel
     */
    public function setForceAutomaticVippsCancel(bool $automaticVippsCancel)
    {
        $this->forceVippsCancel = $automaticVippsCancel;
    }
}
