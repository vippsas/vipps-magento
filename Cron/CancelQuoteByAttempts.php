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

namespace Vipps\Payment\Cron;

use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\Exception\{CouldNotSaveException, NoSuchEntityException};
use Magento\Quote\Api\{CartRepositoryInterface};
use Magento\Quote\Model\{Quote, ResourceModel\Quote\CollectionFactory};
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\{Api\CommandManagerInterface,
    Api\Data\QuoteCancellationInterface,
    Api\Data\QuoteInterface,
    Gateway\Exception\VippsException,
    Gateway\Transaction\Transaction,
    Gateway\Transaction\TransactionBuilder,
    Model\Order\Cancellation\Config,
    Model\Quote\CancelFacade,
    Model\ResourceModel\Quote\Collection as VippsQuoteCollection,
    Model\ResourceModel\Quote\CollectionFactory as VippsQuoteCollectionFactory};
use Vipps\Payment\Model\QuoteManagement as QuoteMonitorManagement;

/**
 * Class FetchOrderStatus
 * @package Vipps\Payment\Cron
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CancelQuoteByAttempts
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
     * @var QuoteMonitorManagement
     */
    private $quoteManagement;

    /**
     * @var Config
     */
    private $cancellationConfig;

    /**
     * @var CancelFacade
     */
    private $cancellationFacade;
    /**
     * @var VippsQuoteCollectionFactory
     */
    private $vippsQuoteCollectionFactory;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * FetchOrderFromVipps constructor.
     *
     * @param CollectionFactory $quoteCollectionFactory
     * @param CommandManagerInterface $commandManager
     * @param TransactionBuilder $transactionBuilder
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param ScopeCodeResolver $scopeCodeResolver
     * @param QuoteMonitorManagement $quoteManagement
     * @param Config $cancellationConfig
     * @param CancelFacade $cancellationFacade
     * @param VippsQuoteCollectionFactory $vippsQuoteCollectionFactory
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        CollectionFactory $quoteCollectionFactory,
        CommandManagerInterface $commandManager,
        TransactionBuilder $transactionBuilder,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ScopeCodeResolver $scopeCodeResolver,
        QuoteMonitorManagement $quoteManagement,
        Config $cancellationConfig,
        CancelFacade $cancellationFacade,
        VippsQuoteCollectionFactory $vippsQuoteCollectionFactory,
        CartRepositoryInterface $cartRepository
    ) {
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->commandManager = $commandManager;
        $this->transactionBuilder = $transactionBuilder;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->scopeCodeResolver = $scopeCodeResolver;
        $this->quoteManagement = $quoteManagement;
        $this->cancellationConfig = $cancellationConfig;
        $this->cancellationFacade = $cancellationFacade;
        $this->vippsQuoteCollectionFactory = $vippsQuoteCollectionFactory;
        $this->cartRepository = $cartRepository;
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
                $quoteCollection = $this->createCollection($currentPage);
                $this->logger->debug(
                    'Fetched quote collection to cancel',
                    ['current page' => $currentPage],
                    ['collection count' => $quoteCollection->count()]
                );
                foreach ($quoteCollection as $quote) {
                    $this->processQuote($quote);
                    usleep(1000000); //delay for 1 second
                }
                $currentPage++;
            } while ($currentPage <= $quoteCollection->getLastPageNumber());
        } finally {
            $this->storeManager->setCurrentStore($currentStore);
        }
    }

    /**
     * Get vipps quote collection to cancel.
     * Conditions are:
     * number of attempts greater than allowed
     *
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
                ['gteq' => $this->cancellationConfig->getAttemptsMaxCount()]
            );

        // Filter not cancelled quotes.
        $collection->addFieldToFilter('is_canceled', ['neq' => 1]);

        return $collection;
    }

    /**
     * Main process
     *
     * @param QuoteInterface $vippsQuote
     *
     * @throws CouldNotSaveException
     */
    private function processQuote(QuoteInterface $vippsQuote)
    {
        $transaction = null;
        $this->logger->info('Start quote cancelling', ['vipps_quote_id' => $vippsQuote->getId()]);

        try {
            $quote = $this->cartRepository->get($vippsQuote->getQuoteId());

            $this->prepareEnv($quote);

            $transaction = $this->fetchOrderStatus($quote->getReservedOrderId());
        } catch (\Throwable $e) {
            $this->logger->critical($e->getMessage(), ['quote_id' => $vippsQuote->getId()]);
        } finally {
            $this
                ->cancellationFacade
                ->cancel(
                    $vippsQuote,
                    QuoteCancellationInterface::CANCEL_TYPE_MAGENTO,
                    __('Number of attempts reached: %1', $this->cancellationConfig->getAttemptsMaxCount()),
                    $quote,
                    $transaction
                );
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
}
