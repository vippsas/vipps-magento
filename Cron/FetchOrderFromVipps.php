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
     * @throws \Exception
     */
    private function processQuote(Quote $quote)
    {
        try {
            $order = null;

            $this->prepareEnv($quote);

            // fetch order status from vipps
            $transaction = $this->fetchOrderStatus($quote->getReservedOrderId());
            if ($transaction->isTransactionAborted()) {
                $this->cancelQuote($quote, 'aborted on vipps side');
                return;
            }

            if ($transaction->isTransactionNotReserved()) {
                if ($this->isQuoteExpired($quote, new \DateInterval('PT5M'))) {
                    $this->cancelQuote($quote, 'expired after 5 min');
                }
                return;
            }

            $order = $this->placeOrder(clone $quote, $transaction);
        } catch (VippsException $e) {
            if ($e->getCode() < ZendResponse::STATUS_CODE_500) {
                $this->cancelQuote($quote, $e);
            }
            $this->logger->critical($e->getMessage() . ', quote id = ' . $quote->getId());
        } catch (\Throwable $e) {
            $this->logger->critical($e->getMessage() . ', quote id = ' . $quote->getId());
        } finally {
            $this->postProcessing($quote, $order);
            usleep(1000000); //delay for 1 second
        }
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
    private function isQuoteExpired(Quote $quote, \DateInterval $interval)
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
     * @param CartInterface $quote
     * @param \Exception|string $info
     */
    private function cancelQuote(CartInterface $quote, $info = null)
    {
        $reservedOrderId = $quote->getReservedOrderId();
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
                'reserved_order_id' => $reservedOrderId
            ]
        );
        $payment = $quote->getPayment();
        $payment->setAdditionalInformation('vipps', $additionalInformation);

        $this->cartRepository->save($quote);

        $this->logger->debug(sprintf(
            'Quote was canceled, id: "%s", reserved_order_id: "%s"',
            $quote->getId(),
            $reservedOrderId
        ));
    }

    /**
     * @param OrderInterface|null $order
     * @param Quote $quote
     *
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    private function postProcessing(Quote $quote, OrderInterface $order = null)
    {
        if (!$order) {
            /** @var Quote $updatedQuote */
            $updatedQuote = $this->cartRepository->get($quote->getEntityId());
            if ($updatedQuote->getReservedOrderId()) {
                // more then 1 day
                if ($this->isQuoteExpired($updatedQuote, new \DateInterval('P1D'))) {
                    $this->commandManager->cancel($updatedQuote->getPayment());
                    $this->cancelQuote($updatedQuote, 'expired after 1 day');
                }
            }
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
