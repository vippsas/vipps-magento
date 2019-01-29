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

namespace Vipps\Payment\Cron;

use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\Exception\{AlreadyExistsException, CouldNotSaveException, InputException, NoSuchEntityException};
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\{CartRepositoryInterface, Data\CartInterface};
use Magento\Quote\Model\{Quote, QuoteRepository, ResourceModel\Quote\CollectionFactory};
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\{Api\CommandManagerInterface,
    Api\Data\QuoteCancellationInterface,
    Gateway\Exception\VippsException,
    Gateway\Transaction\Transaction,
    Gateway\Transaction\TransactionBuilder,
    Model\Order\Cancellation\Config,
    Model\OrderPlace,
    Model\Quote as VippsQuote,
    Model\Quote\AttemptManagement,
    Model\Quote\CancelFacade,
    Model\QuoteManagement as QuoteMonitorManagement,
    Model\ResourceModel\Quote\Collection as VippsQuoteCollection,
    Model\ResourceModel\Quote\CollectionFactory as VippsQuoteCollectionFactory};
use Vipps\Payment\Gateway\Exception\WrongAmountException;

/**
 * Class FetchOrderStatus
 * @package Vipps\Payment\Cron
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class FetchOrderFromVipps
{
    /**
     * Order collection page size
     */
    const COLLECTION_PAGE_SIZE = 100;

    /**
     * @var CollectionFactory
     */
    private $quoteCollectionFactory;

    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * @var OrderPlace
     */
    private $orderPlace;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeCodeResolver
     */
    private $scopeCodeResolver;

    /**
     * @var Config
     */
    private $cancellationConfig;

    /**
     * @var AttemptManagement
     */
    private $attemptManagement;
    /**
     * @var CancelFacade
     */
    private $cancellationFacade;
    /**
     * @var VippsQuoteCollectionFactory
     */
    private $vippsQuoteCollectionFactory;
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * FetchOrderFromVipps constructor.
     * @param CollectionFactory $quoteCollectionFactory
     * @param VippsQuoteCollectionFactory $vippsQuoteCollectionFactory
     * @param QuoteRepository $quoteRepository
     * @param CommandManagerInterface $commandManager
     * @param TransactionBuilder $transactionBuilder
     * @param OrderPlace $orderManagement
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param ScopeCodeResolver $scopeCodeResolver
     * @param QuoteMonitorManagement $quoteManagement
     * @param Config $cancellationConfig
     * @param AttemptManagement $attemptManagement
     * @param CancelFacade $cancellationFacade
     */
    public function __construct(
        CollectionFactory $quoteCollectionFactory,
        VippsQuoteCollectionFactory $vippsQuoteCollectionFactory,
        QuoteRepository $quoteRepository,
        CommandManagerInterface $commandManager,
        TransactionBuilder $transactionBuilder,
        OrderPlace $orderManagement,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ScopeCodeResolver $scopeCodeResolver,
        Config $cancellationConfig,
        AttemptManagement $attemptManagement,
        CancelFacade $cancellationFacade
    ) {
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->commandManager = $commandManager;
        $this->transactionBuilder = $transactionBuilder;
        $this->orderPlace = $orderManagement;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->scopeCodeResolver = $scopeCodeResolver;
        $this->cancellationConfig = $cancellationConfig;
        $this->attemptManagement = $attemptManagement;
        $this->cancellationFacade = $cancellationFacade;
        $this->vippsQuoteCollectionFactory = $vippsQuoteCollectionFactory;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Create orders from Vipps that are not created in Magento yet
     *
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function execute()
    {
        try {
            $currentStore = $this->storeManager->getStore()->getId();
            $currentPage = 1;
            do {
                $vippsQuoteCollection = $this->createCollection($currentPage);
                $this->logger->debug(
                    'Fetched payment details',
                    ['page' => $currentPage, 'count' => $vippsQuoteCollection->count()]
                );
                /** @var VippsQuote $vippsQuote */
                foreach ($vippsQuoteCollection as $vippsQuote) {
                    $this->processQuote($vippsQuote);
                    usleep(1000000); //delay for 1 second
                }
                $currentPage++;
            } while ($currentPage <= $vippsQuoteCollection->getLastPageNumber());
        } finally {
            $this->storeManager->setCurrentStore($currentStore);
        }
    }

    /**
     * @param $currentPage
     *
     * @return VippsQuoteCollection
     */
    private function createCollection($currentPage)
    {
        /** @var VippsQuoteCollection $collection */
        $collection = $this->vippsQuoteCollectionFactory->create();

        $collection
            ->setPageSize(self::COLLECTION_PAGE_SIZE)
            ->setCurPage($currentPage)
            ->addFieldToFilter(
                'attempts',
                [
                    ['lt' => $this->cancellationConfig->getAttemptsMaxCount()],
                    ['null' => 1]
                ]
            )
            ->addFieldToFilter('is_canceled', ['neq' => 1]); // Filter not cancelled quotes.

        return $collection;
    }

    /**
     * @param VippsQuote $vippsQuote
     * @throws CouldNotSaveException
     */
    private function processQuote(VippsQuote $vippsQuote)
    {
        try {
            // Register empty attempt.
            $attempt = $this->attemptManagement->createAttempt($vippsQuote);

            // Get Magento Quote for processing.
            $quote = $this->quoteRepository->get($vippsQuote->getQuoteId());
            $this->prepareEnv($quote);

            $transaction = $this->fetchOrderStatus($quote->getReservedOrderId());

            if ($transaction->isTransactionAborted()) {
                $attempt->setMessage('Transaction was cancelled on Vipps side');
                // Create cancellation if transaction was aborted on Vipps side.
                $this
                    ->cancellationFacade
                    ->cancel(
                        $vippsQuote,
                        QuoteCancellationInterface::CANCEL_TYPE_VIPPS,
                        'Transaction was cancelled on Vipps side',
                        $quote,
                        $transaction
                    );
            } else {
                $this->placeOrder($quote, $transaction);
            }
        } catch (\Throwable $e) {
            $this->logger->critical($e->getMessage(), ['vipps_quote_id' => $vippsQuote->getId()]);
            if (isset($attempt)) {
                $attempt->setMessage($e->getMessage());
            }
        } finally {
            if (isset($attempt)) {
                // Simply save the attempt.
                $this->attemptManagement->save($attempt);
            }
        }
    }

    /**
     * Prepare environment.
     *
     * @param Quote $quote
     */
    private function prepareEnv(Quote $quote)
    {
        // set quote store as current store
        $this->scopeCodeResolver->clean();
        $this->storeManager->setCurrentStore($quote->getStore()->getId());
    }

    /**
     * @param $orderId
     *
     * @return Transaction
     * @throws VippsException
     */
    private function fetchOrderStatus($orderId)
    {
        $response = $this->commandManager->getOrderStatus($orderId);
        return $this->transactionBuilder->setData($response)->build();
    }

    /**
     * @param CartInterface $quote
     * @param Transaction $transaction
     *
     * @return OrderInterface|null
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws VippsException
     * @throws LocalizedException
     * @throws WrongAmountException
     */
    private function placeOrder(CartInterface $quote, Transaction $transaction)
    {
        $order = $this->orderPlace->execute($quote, $transaction);
        if ($order) {
            $this->logger->debug(sprintf('Order placed: "%s"', $order->getIncrementId()));
        } else {
            $this->logger->critical(sprintf(
                'Order has not been placed, quote id: "%s", reserved_order_id: "%s"',
                $quote->getId(),
                $quote->getReservedOrderId()
            ));
        }
        return $order;
    }

    /**
     * @deprecated
     * @param Quote $quote
     * @param \DateInterval $interval
     *
     * @return bool
     */
    private function isQuoteExpired(Quote $quote, \DateInterval $interval) //@codingStandardsIgnoreLine
    {
        $quoteExpiredAt = (new \DateTime($quote->getUpdatedAt()))->add($interval); //@codingStandardsIgnoreLine
        $isQuoteExpired = !$quoteExpiredAt->diff(new \DateTime())->invert; //@codingStandardsIgnoreLine
        return $isQuoteExpired;
    }
}
