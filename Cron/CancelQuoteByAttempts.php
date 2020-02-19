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
use Magento\Framework\Exception\{CouldNotSaveException};
use Magento\Quote\Api\{CartRepositoryInterface};
use Magento\Quote\Model\{ResourceModel\Quote\CollectionFactory};
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\{Api\Data\QuoteInterface,
    Api\Data\QuoteStatusInterface,
    Model\Order\Cancellation\Config,
    Model\Quote\AttemptManagement,
    Model\Quote\CancelFacade,
    Model\ResourceModel\Quote\Collection as VippsQuoteCollection,
    Model\ResourceModel\Quote\CollectionFactory as VippsQuoteCollectionFactory};

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
     * @var AttemptManagement
     */
    private $attemptManagement;

    /**
     * FetchOrderFromVipps constructor.
     *
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param ScopeCodeResolver $scopeCodeResolver
     * @param Config $cancellationConfig
     * @param CancelFacade $cancellationFacade
     * @param VippsQuoteCollectionFactory $vippsQuoteCollectionFactory
     * @param CartRepositoryInterface $cartRepository
     * @param AttemptManagement $attemptManagement
     */
    public function __construct(
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ScopeCodeResolver $scopeCodeResolver,
        Config $cancellationConfig,
        CancelFacade $cancellationFacade,
        VippsQuoteCollectionFactory $vippsQuoteCollectionFactory,
        CartRepositoryInterface $cartRepository,
        AttemptManagement $attemptManagement
    ) {
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->scopeCodeResolver = $scopeCodeResolver;
        $this->cancellationConfig = $cancellationConfig;
        $this->cancellationFacade = $cancellationFacade;
        $this->vippsQuoteCollectionFactory = $vippsQuoteCollectionFactory;
        $this->cartRepository = $cartRepository;
        $this->attemptManagement = $attemptManagement;
    }

    /**
     * Create orders from Vipps that are not created in Magento yet
     *
     * @throws CouldNotSaveException
     */
    public function execute()
    {
        try {
            $currentStore = $this->storeManager->getStore()->getId();
            $currentPage = 1;
            do {
                $quoteCollection = $this->createCollection($currentPage);
                $this->logger->debug('Fetched quote collection to cancel');
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

        // Filter processing cancelled quotes.
        $collection->addFieldToFilter(
            QuoteStatusInterface::FIELD_STATUS,
            ['in' => [
                QuoteStatusInterface::STATUS_NEW,
                QuoteStatusInterface::STATUS_PLACE_FAILED,
                QuoteStatusInterface::STATUS_PENDING
            ]]
        );

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
        $this->logger->info('Start quote cancelling', ['vipps_quote_id' => $vippsQuote->getId()]);

        try {
            $this->prepareEnv($vippsQuote);

            if ($this->cancellationConfig->isAutomatic($vippsQuote->getStoreId())) {
                $quote = $this->cartRepository->get($vippsQuote->getQuoteId());

                $attempt = $this->attemptManagement->createAttempt($vippsQuote, true);

                $attempt
                    ->setMessage(__(
                        'Max number of attempts reached (%1)',
                        $this->cancellationConfig->getAttemptsMaxCount()
                    ));

                $this
                    ->cancellationFacade
                    ->cancel($vippsQuote, $quote);
            }
        } catch (\Throwable $e) {
            $this->logger->critical($e->getMessage(), ['quote_id' => $vippsQuote->getId()]);

            if (isset($attempt)) {
                $attempt->setMessage($e->getMessage());
                $this->attemptManagement->save($attempt);
            }
        }
    }

    /**
     * Prepare environment.
     *
     * @param QuoteInterface $quote
     */
    private function prepareEnv(QuoteInterface $quote)
    {
        // set quote store as current store
        $this->scopeCodeResolver->clean();
        $this->storeManager->setCurrentStore($quote->getStoreId());
    }
}
