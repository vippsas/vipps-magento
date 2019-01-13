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
use Magento\Quote\Model\{Quote, ResourceModel\Quote\Collection, ResourceModel\Quote\CollectionFactory};
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\{Api\CommandManagerInterface,
    Api\Monitoring\Data\QuoteCancellationInterface,
    Api\Monitoring\Data\QuoteInterface,
    Gateway\Exception\VippsException,
    Gateway\Transaction\Transaction,
    Gateway\Transaction\TransactionBuilder,
    Model\Order\Cancellation\Config,
    Model\OrderPlace};
use Vipps\Payment\Gateway\Exception\WrongAmountException;
use Vipps\Payment\Model\Monitoring\{Quote\AttemptManagement,
    Quote\CancellationFacade,
    Quote\CancellationFactory,
    Quote\CancellationRepository,
    QuoteManagement as QuoteMonitorManagement,
    QuoteRepository as QuoteMonitorRepository};
use Vipps\Payment\Model\ResourceModel\Monitoring\Quote\Cancellation\Type as CancellationTypeResource;

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
     * @var QuoteMonitorManagement
     */
    private $quoteManagement;
    /**
     * @var Config
     */
    private $cancellationConfig;
    /**
     * @var CancellationFactory
     */
    private $cancellationFactory;
    /**
     * @var CancellationRepository
     */
    private $cancellationRepository;
    /**
     * @var QuoteMonitorRepository
     */
    private $quoteMonitorRepository;
    /**
     * @var AttemptManagement
     */
    private $attemptManagement;
    /**
     * @var CancellationFacade
     */
    private $cancellationFacade;

    /**
     * FetchOrderFromVipps constructor.
     * @param CollectionFactory $quoteCollectionFactory
     * @param CommandManagerInterface $commandManager
     * @param TransactionBuilder $transactionBuilder
     * @param OrderPlace $orderManagement
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param ScopeCodeResolver $scopeCodeResolver
     * @param QuoteMonitorManagement $quoteManagement
     * @param Config $cancellationConfig
     * @param AttemptManagement $attemptManagement
     * @param CancellationFacade $cancellationFacade
     */
    public function __construct(
        CollectionFactory $quoteCollectionFactory,
        CommandManagerInterface $commandManager,
        TransactionBuilder $transactionBuilder,
        OrderPlace $orderManagement,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ScopeCodeResolver $scopeCodeResolver,
        QuoteMonitorManagement $quoteManagement,
        Config $cancellationConfig,
        AttemptManagement $attemptManagement,
        CancellationFacade $cancellationFacade
    ) {
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->commandManager = $commandManager;
        $this->transactionBuilder = $transactionBuilder;
        $this->orderPlace = $orderManagement;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->scopeCodeResolver = $scopeCodeResolver;
        $this->quoteManagement = $quoteManagement;
        $this->cancellationConfig = $cancellationConfig;
        $this->attemptManagement = $attemptManagement;
        $this->cancellationFacade = $cancellationFacade;
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
                    'Fetched payment details',
                    ['page' => $currentPage, 'count' => $quoteCollection->count()]
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
     * @param $currentPage
     *
     * @return Collection
     */
    private function createCollection($currentPage)
    {
        /** @var Collection $collection */
        $collection = $this->quoteCollectionFactory->create();

        $collection
            ->setPageSize(self::COLLECTION_PAGE_SIZE)
            ->setCurPage($currentPage)
            ->addFieldToSelect(['entity_id', 'reserved_order_id', 'store_id', 'updated_at'])
            ->join(
                ['p' => $collection->getTable('quote_payment')],
                'main_table.entity_id = p.quote_id',
                ['p.method']
            )
            ->addFieldToFilter('p.method', ['eq' => 'vipps']);

        // Filter quotes that reached max count of queries. They will be cancelled by another job.
        $collection
            ->getSelect()
            ->joinLeft(
                ['vq' => $collection->getTable('vipps_quote')],
                'main_table.entity_id = vq.quote_id',
                ['attempts']
            );

        $collection->addFieldToFilter('vq.attempts', [
                ['lt' => $this->cancellationConfig->getAttemptsMaxCount()],
                ['null' => 1]
            ]
        );

        // Filter not cancelled quotes.
        $collection->addFieldToFilter('vq.is_canceled', ['neq' => 1]);

        // @todo discuss if this legacy should be removed.
        $collection->addFieldToFilter('main_table.is_active', ['in' => ['0']]);
        $collection->addFieldToFilter('main_table.updated_at', ['to' => date("Y-m-d H:i:s", time() - 300)]); // 5min
        $collection->addFieldToFilter('main_table.reserved_order_id', ['neq' => '']);
        return $collection;
    }

    /**
     * @param Quote $quote
     * @throws CouldNotSaveException
     */
    private function processQuote(Quote $quote)
    {
        try {
            $this->quoteManagement->loadExtensionAttribute($quote);
            $attempt = $this->createMonitoringAttempt($quote);

            // Load vipps quote monitoring as extension attribute.
            $this->prepareEnv($quote);

            $transaction = $this->fetchOrderStatus($quote->getReservedOrderId());

            if ($transaction->isTransactionAborted()) {
                $attempt->setMessage('Transaction was cancelled on Vipps side');
                // Create cancellation if transaction was aborted on Vipps side.
                $this
                    ->cancellationFacade
                    ->cancelMagento(
                        $quote,
                        QuoteCancellationInterface::CANCEL_TYPE_VIPPS,
                        'Transaction was cancelled on Vipps side',
                        $transaction
                    );
            } else {
                $this->placeOrder($quote, $transaction);
            }
        } catch (\Throwable $e) {
            $this->logger->critical($e->getMessage(), ['quote_id' => $quote->getId()]);
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
     * Create monitoring attempt.
     *
     * @param CartInterface $quote
     * @return \Vipps\Payment\Model\Monitoring\Quote\Attempt
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     */
    private function createMonitoringAttempt(CartInterface $quote)
    {
        /** @var \Vipps\Payment\Model\Monitoring\Quote $monitoringQuote */
        $monitoringQuote = $quote
            ->getExtensionAttributes()
            ->getVippsMonitoring();

        return $this->attemptManagement->createAttempt($monitoringQuote);
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
