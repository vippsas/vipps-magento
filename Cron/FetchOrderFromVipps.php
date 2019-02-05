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
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Quote\Api\{Data\CartInterface};
use Magento\Quote\Model\{QuoteRepository, ResourceModel\Quote\CollectionFactory};
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\{Api\CommandManagerInterface,
    Api\Data\QuoteStatusInterface,
    Gateway\Exception\VippsException,
    Gateway\Transaction\Transaction,
    Gateway\Transaction\TransactionBuilder,
    Model\Order\Cancellation\Config,
    Model\OrderPlace,
    Model\Quote as VippsQuote,
    Model\Quote\AttemptManagement,
    Model\QuoteRepository as VippsQuoteRepository,
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
     * @var VippsQuoteCollectionFactory
     */
    private $vippsQuoteCollectionFactory;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var VippsQuoteRepository
     */
    private $vippsQuoteRepository;

    /**
     * @var DateTimeFactory
     */
    private $dateTimeFactory;

    /**
     * FetchOrderFromVipps constructor.
     * @param CollectionFactory $quoteCollectionFactory
     * @param VippsQuoteCollectionFactory $vippsQuoteCollectionFactory
     * @param VippsQuoteRepository $vippsQuoteRepository
     * @param QuoteRepository $quoteRepository
     * @param CommandManagerInterface $commandManager
     * @param TransactionBuilder $transactionBuilder
     * @param OrderPlace $orderManagement
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param ScopeCodeResolver $scopeCodeResolver
     * @param Config $cancellationConfig
     * @param DateTimeFactory $dateTimeFactory
     * @param AttemptManagement $attemptManagement
     */
    public function __construct(
        CollectionFactory $quoteCollectionFactory,
        VippsQuoteCollectionFactory $vippsQuoteCollectionFactory,
        VippsQuoteRepository $vippsQuoteRepository,
        QuoteRepository $quoteRepository,
        CommandManagerInterface $commandManager,
        TransactionBuilder $transactionBuilder,
        OrderPlace $orderManagement,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ScopeCodeResolver $scopeCodeResolver,
        Config $cancellationConfig,
        DateTimeFactory $dateTimeFactory,
        AttemptManagement $attemptManagement
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
        $this->vippsQuoteCollectionFactory = $vippsQuoteCollectionFactory;
        $this->quoteRepository = $quoteRepository;
        $this->vippsQuoteRepository = $vippsQuoteRepository;
        $this->dateTimeFactory = $dateTimeFactory;
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
            ->addFieldToFilter(
                QuoteStatusInterface::FIELD_STATUS,
                ['in' => [QuoteStatusInterface::STATUS_NEW, QuoteStatusInterface::STATUS_PROCESSING]]
            ); // Filter new and place failed quotes.

        return $collection;
    }

    /**
     * @param VippsQuote $vippsQuote
     * @throws CouldNotSaveException
     */
    private function processQuote(VippsQuote $vippsQuote)
    {
        $vippsQuoteStatus = QuoteStatusInterface::STATUS_PROCESSING;
        $attemptMessage = '';

        try {
            // Register new attempt.
            $attempt = $this->attemptManagement->createAttempt($vippsQuote);
            $this->prepareEnv($vippsQuote);

            // Get Magento Quote for processing.
            $quote = $this->quoteRepository->get($vippsQuote->getQuoteId());
            $transaction = $this->fetchOrderStatus($quote->getReservedOrderId());
            if ($transaction->isTransactionAborted()) {
                $attemptMessage = __('Transaction was cancelled in Vipps');
                $vippsQuoteStatus = QuoteStatusInterface::STATUS_CANCELED;
            } else {
                $order = $this->placeOrder($quote, $transaction);
                if ($order) {
                    $vippsQuoteStatus = QuoteStatusInterface::STATUS_PLACED;
                    $attemptMessage = __('Placed');
                }

                if ($transaction->isInitiate() && $this->isQuoteExpired($vippsQuote)) {
                    $vippsQuoteStatus = QuoteStatusInterface::STATUS_EXPIRED;
                    $attemptMessage = __('Transaction has been expired');
                }
            }
        } catch (\Throwable $e) {
            $vippsQuoteStatus = $this->isMaxAttemptsReached($vippsQuote)
                ? QuoteStatusInterface::STATUS_PLACE_FAILED : QuoteStatusInterface::STATUS_PROCESSING;

            $this->logger->critical($e->getMessage(), ['vipps_quote_id' => $vippsQuote->getId()]);
            $attemptMessage = $e->getMessage();
        } finally {
            $vippsQuote->setStatus($vippsQuoteStatus);
            $this->vippsQuoteRepository->save($vippsQuote);

            if (isset($attempt)) {
                $attempt->setMessage($attemptMessage);
                $this->attemptManagement->save($attempt);
            }
        }
    }

    /**
     * Prepare environment.
     *
     * @param VippsQuote $quote
     */
    private function prepareEnv(VippsQuote $quote)
    {
        // set quote store as current store
        $this->scopeCodeResolver->clean();

        $this->storeManager->setCurrentStore($quote->getStoreId());
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
     * Validate Vipps Quote expiration.
     *
     * @param $vippsQuote
     * @return bool
     * @throws \Exception
     */
    private function isQuoteExpired($vippsQuote)
    {
        $createdAt = $this->dateTimeFactory->create($vippsQuote->getCreatedAt());

        $interval = new \DateInterval("PT{$this->cancellationConfig->getInactivityTime()}M");

        $createdAt->add($interval);

        return !$createdAt->diff($this->dateTimeFactory->create())->invert;
    }

    private function isMaxAttemptsReached(VippsQuote $vippsQuote)
    {
        return $vippsQuote->getAttempts() >= $this->cancellationConfig->getAttemptsMaxCount();
    }
}
