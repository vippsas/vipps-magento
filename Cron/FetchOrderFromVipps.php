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

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\Exception\{CouldNotSaveException, NoSuchEntityException, AlreadyExistsException, InputException};
use Magento\Quote\Api\{CartRepositoryInterface, Data\CartInterface};
use Magento\Quote\Model\{ResourceModel\Quote\Collection, ResourceModel\Quote\CollectionFactory, Quote, Quote\Payment};
use Magento\Sales\Api\Data\OrderInterface;
use Vipps\Payment\{
    Api\CommandManagerInterface,
    Gateway\Exception\VippsException,
    Gateway\Transaction\Transaction,
    Model\OrderPlace,
    Gateway\Transaction\TransactionBuilder
};
use Vipps\Payment\Gateway\Exception\WrongAmountException;
use Zend\Http\Response as ZendResponse;
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\LocalizedException;

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
     * @var string
     */
    const MAX_NUMBER_OF_ATTEMPTS = 3;

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
     * FetchOrderFromVipps constructor.
     *
     * @param CollectionFactory $quoteCollectionFactory
     * @param CommandManagerInterface $commandManager
     * @param TransactionBuilder $transactionBuilder
     * @param OrderPlace $orderManagement
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param ScopeCodeResolver $scopeCodeResolver
     */
    public function __construct(
        CollectionFactory $quoteCollectionFactory,
        CommandManagerInterface $commandManager,
        TransactionBuilder $transactionBuilder,
        OrderPlace $orderManagement,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ScopeCodeResolver $scopeCodeResolver
    ) {
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->commandManager = $commandManager;
        $this->transactionBuilder = $transactionBuilder;
        $this->orderPlace = $orderManagement;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->scopeCodeResolver = $scopeCodeResolver;
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
                $this->logger->debug(sprintf(
                    'Fetched payment details, page: "%s", quotes: "%s"',
                    $currentPage,
                    $quoteCollection->count() //@codingStandardsIgnoreLine
                ));
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
     * Main process
     *
     * @param Quote $quote
     *
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    private function processQuote(Quote $quote)
    {
        try {
            $order = null;
            $transaction = null;
            $currentException = null;

            $this->prepareEnv($quote);

            $transaction = $this->fetchOrderStatus($quote->getReservedOrderId());

            if ($transaction->isTransactionAborted()) {
                $this->cancelQuote($quote, $transaction, 'canceled on vipps side');
            } else {
                $order = $this->placeOrder(clone $quote, $transaction);
            }
        } catch (\Throwable $e) {
            $currentException = $e; //@codingStandardsIgnoreLine
            $this->logger->critical($e->getMessage() . ', quote id = ' . $quote->getId());
        } finally {
            if ($order) {
                // if order exists - nothing to do, all good
                return;
            }
            /** @var Quote $quote */
            $quote = $this->cartRepository->get($quote->getEntityId());
            if (!$quote->getReservedOrderId()) {
                // if quote does not have reserved order id - such quote will not be processed next time
                return;
            }

            // count not success (order not created) attempts of this process
            if ($this->countAttempts($quote) >= $this->getMaxNumberOfAttempts()) {
                $this->cancelQuote(
                    $quote,
                    $transaction,
                    sprintf(
                        'canceled after "%s" attempts, last error "%s"',
                        $this->getMaxNumberOfAttempts(),
                        $currentException ? $currentException->getMessage() : 'n/a'
                    )
                );
                return;
            }
        }
    }

    /**
     * @param Quote|CartInterface $quote
     *
     * @return int
     * @throws LocalizedException
     */
    private function countAttempts($quote)
    {
        $additionalInfo = $quote->getPayment()->getAdditionalInformation();
        $attempts = (int)($additionalInfo['vipps']['attempts'] ?? 0);

        $additionalInfo['vipps']['attempts'] = ++$attempts;
        $quote->getPayment()->setAdditionalInformation($additionalInfo);

        if ($attempts < $this->getMaxNumberOfAttempts()) {
            $this->cartRepository->save($quote);
        }

        return $attempts;
    }

    /**
     * @return int
     */
    private function getMaxNumberOfAttempts()
    {
        return (int)self::MAX_NUMBER_OF_ATTEMPTS;
    }

    /**
     * @param Quote $quote
     */
    private function prepareEnv(Quote $quote)
    {
        // set quote store as current store
        $this->scopeCodeResolver->clean();
        $this->storeManager->setCurrentStore($quote->getStore()->getId());
    }

    /**
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
     * Cancel quote by setting reserved_order_id to null
     *
     * @param CartInterface|Quote $quote
     * @param Transaction|null $transaction
     * @param null $info
     *
     * @throws LocalizedException
     */
    private function cancelQuote(CartInterface $quote, Transaction $transaction = null, $info = null)
    {
        $savedQuote = clone $quote;
        $quote->setReservedOrderId(null);

        $additionalInformation = [];
        if ($info instanceof \Exception) {
            $additionalInformation = [
                'cancel_reason_code' => $info->getCode(),
                'cancel_reason_phrase' => $info->getMessage()
            ];
        } elseif (\is_string($info)) {
            $additionalInformation['cancel_reason_phrase'] = $info;
        }

        $additionalInformation = array_merge(
            $additionalInformation,
            [
                'reserved_order_id' => $savedQuote->getReservedOrderId()
            ]
        );
        $payment = $quote->getPayment();
        $existingAdditionalInfo = $payment->getAdditionalInformation()['vipps'] ?? [];
        $payment->setAdditionalInformation('vipps', array_merge($existingAdditionalInfo, $additionalInformation));

        $this->cartRepository->save($quote);

        $this->logger->debug(sprintf(
            'Quote was canceled, id: "%s", reserved_order_id: "%s", cancel reason "%s"',
            $quote->getId(),
            $savedQuote->getReservedOrderId(),
            $additionalInformation['cancel_reason_phrase']
        ));

        // cancel order on vipps side
        if ($transaction && $transaction->isTransactionReserved()) {
            $this->commandManager->cancel($savedQuote->getPayment());
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

        $collection->setPageSize(self::COLLECTION_PAGE_SIZE);
        $collection->setCurPage($currentPage);
        $collection->addFieldToSelect(['entity_id', 'reserved_order_id', 'store_id', 'updated_at']); //@codingStandardsIgnoreLine
        $collection->join(
            ['p' => $collection->getTable('quote_payment')],
            'main_table.entity_id = p.quote_id',
            ['p.method']
        );
        $collection->addFieldToFilter('p.method', ['eq' => 'vipps']);
        $collection->addFieldToFilter('main_table.is_active', ['in' => ['0']]);
        $collection->addFieldToFilter('main_table.updated_at', ['to' => date("Y-m-d H:i:s", time() - 300)]); // 5min
        $collection->addFieldToFilter('main_table.reserved_order_id', ['neq' => '']);
        return $collection;
    }
}
