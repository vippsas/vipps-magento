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
namespace Vipps\Payment\Model;

use Magento\Framework\{
    Api\SearchCriteriaBuilder, Exception\CouldNotSaveException, Exception\LocalizedException, Exception\NoSuchEntityException, Exception\NotFoundException
};
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\{
    OrderManagementInterface, Data\OrderInterface, OrderRepositoryInterface
};
use Magento\Sales\Model\{
    Order, Order\Payment\Transaction as PaymentTransaction,
    Order\Payment\Processor, Order\StatusResolver, Order\Payment
};
use Vipps\Payment\{
    Api\CommandManagerInterface, Gateway\Transaction\Transaction, Model\Helper\OrderPlace, Model\Helper\QuoteUpdater
};

/**
 * Class OrderManagement
 * @package Vipps\Payment\Model
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderManagement
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var OrderPlace
     */
    private $orderHelper;

    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var StatusResolver
     */
    private $statusResolver;

    /**
     * @var QuoteUpdater
     */
    private $quoteUpdater;

    /**
     * OrderManagement constructor.
     *
     * @param CommandManagerInterface $commandManager
     * @param OrderRepositoryInterface $orderRepository
     * @param QuoteRepository $quoteRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderManagementInterface $orderManagement
     * @param OrderPlace $orderHelper
     * @param Processor $processor
     * @param StatusResolver $statusResolver
     * @param QuoteUpdater $quoteUpdater
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        OrderRepositoryInterface $orderRepository,
        QuoteRepository $quoteRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderManagementInterface $orderManagement,
        OrderPlace $orderHelper,
        Processor $processor,
        StatusResolver $statusResolver,
        QuoteUpdater $quoteUpdater
    ) {
        $this->commandManager = $commandManager;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderManagement = $orderManagement;
        $this->orderHelper = $orderHelper;
        $this->processor = $processor;
        $this->statusResolver = $statusResolver;
        $this->quoteUpdater = $quoteUpdater;
    }

    /**
     * Place Order method.
     *
     * @param $incrementId
     * @param Transaction $transaction
     * @throws LocalizedException
     */
    public function place($incrementId, Transaction $transaction)
    {
        $order = $this->getOrderByIncrementId($incrementId);
        if (!$order) {
            $this->validateTransactionStatus($transaction->getStatus());
            $quote = $this->getQuoteByReservedOrderId($incrementId);
            if ($quote) {
                try {
                    $this->quoteUpdater->execute($quote, $transaction);
                    $orderId = $this->orderHelper->execute($quote);
                } catch (CouldNotSaveException $e) {
                    throw new LocalizedException(__('Order conformation will be sent later!'));
                }
                $order = $this->orderRepository->get($orderId);
            } else {
                throw new LocalizedException(__('Can\'t place your order. Please try again later. '));
            }
        }
        $this->processGatewayTransaction($order->getIncrementId(), $transaction);
    }

    /**
     * @param $reservedOrderId
     *
     * @return Quote|mixed
     * @throws NotFoundException
     */
    public function getQuoteByReservedOrderId($reservedOrderId)
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('reserved_order_id', $reservedOrderId, 'eq')
            ->create();
        $quoteList = $this->quoteRepository->getList($searchCriteria)->getItems();
        $quote = current($quoteList);
        if (!$quote) {
            throw new NotFoundException(__('Requested Quote does not exist'));
        }
        return $quote;
    }

    /**
     * Authorize action.
     *
     * @param $incrementId
     * @param Transaction $transaction
     */
    public function authorize($incrementId, Transaction $transaction)
    {
        $order = $this->getOrderByIncrementId($incrementId);
        if ($order->getState() !== Order::STATE_NEW) {
            return;
        }

        /** @var Payment $payment */
        $payment = $order->getPayment();
        $transactionId = $transaction->getTransactionId();
        $payment->setTransactionId($transactionId);
        $payment->setIsTransactionClosed(false);
        $payment->setTransactionAdditionalInfo(
            PaymentTransaction::RAW_DETAILS,
            $transaction->getTransactionInfo()->getData()
        );

        // preconditions
        $totalDue = $order->getTotalDue();
        $baseTotalDue = $order->getBaseTotalDue();

        // do authorize
        $this->processor->authorize($payment, false, $baseTotalDue);
        // base amount will be set inside
        $payment->setAmountAuthorized($totalDue);
        $this->orderRepository->save($order);

        $this->notify($order);
    }

    /**
     * @param $incrementId
     * @param Transaction $transaction
     */
    public function registerCaptureNotification($incrementId, Transaction $transaction)
    {
        $order = $this->getOrderByIncrementId($incrementId);
        if ($order->getState() !== Order::STATE_NEW) {
            return;
        }

        // preconditions
        $totalDue = $order->getTotalDue();
        $baseTotalDue = $order->getBaseTotalDue();

        /** @var Payment $payment */
        $payment = $order->getPayment();
        $payment->setAmountAuthorized($totalDue);
        $payment->setBaseAmountAuthorized($baseTotalDue);

        $transactionId = $transaction->getTransactionId();
        $payment->setTransactionId($transactionId);
        $payment->setTransactionAdditionalInfo(
            PaymentTransaction::RAW_DETAILS,
            $transaction->getTransactionInfo()->getData()
        );

        // do capture
        $this->processor->registerCaptureNotification($payment, $baseTotalDue);
        $this->orderRepository->save($order);

        $this->notify($order);
    }

    /**
     * @param $incrementId
     * @param Transaction $transaction
     */
    public function cancel($incrementId, Transaction $transaction)
    {
        $order = $this->getOrderByIncrementId($incrementId);
        /** @var Payment $payment */
        $payment = $order->getPayment();

        $transactionId = $transaction->getTransactionId();
        $payment->setTransactionId($transactionId);
        $payment->setTransactionAdditionalInfo(
            PaymentTransaction::RAW_DETAILS,
            $transaction->getTransactionInfo()->getData()
        );

        $this->orderManagement->cancel($order->getEntityId());
    }

    /**
     * Update order state and status
     *
     * @param $incrementId
     * @param $state
     * @param null $status
     */
    public function changeStateAndStatus($incrementId, $state, $status = null)
    {
        $order = $this->getOrderByIncrementId($incrementId);

        /** @var Order $order */
        $status = $status ? $status : $this->statusResolver->getOrderStatusByState($order, $state);

        if ($order->getState() !== $state || $order->getStatus() !== $status) {
            $order->setState($state);
            $order->setStatus($status);
            $this->orderRepository->save($order);
        }
    }

    /**
     * Retrieve an order from repository by order increment id
     *
     * @param string|int $incrementId
     *
     * @return OrderInterface
     */
    public function getOrderByIncrementId($incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', $incrementId, 'eq')->create();
        $orderList = $this->orderRepository->getList($searchCriteria)->getItems();

        return current($orderList);
    }

    /**
     * Process order based on transaction data received from gateway.
     *
     * @param $incrementId
     * @param Transaction $transaction
     */
    private function processGatewayTransaction($incrementId, Transaction $transaction)
    {
        $transactionStatus = $transaction->getStatus();
        switch ($transactionStatus) {
            case Transaction::TRANSACTION_STATUS_INITIATE:
            case Transaction::TRANSACTION_STATUS_REGISTER:
                $this->changeStateAndStatus($incrementId, Order::STATE_PENDING_PAYMENT);
                break;
            case Transaction::TRANSACTION_STATUS_RESERVE:
            case Transaction::TRANSACTION_STATUS_RESERVED:
                $this->authorize($incrementId, $transaction);
                break;
            case Transaction::TRANSACTION_STATUS_SALE:
                $this->registerCaptureNotification($incrementId, $transaction);
                break;
            case Transaction::TRANSACTION_STATUS_CANCEL:
            case Transaction::TRANSACTION_STATUS_AUTOCANCEL:
                $this->cancel($incrementId, $transaction);
                break;
        }
    }

    /**
     * Send order conformation email if not sent
     *
     * @param Order|OrderInterface $order
     */
    private function notify($order)
    {
        if ($order->getCanSendNewEmailFlag() && !$order->getEmailSent()) {
            $this->orderManagement->notify($order->getEntityId());
        }
    }

    /**
     * @param $transactionStatus
     *
     * @throws LocalizedException
     */
    private function validateTransactionStatus($transactionStatus)
    {
        $initiatedStatuses = [
            Transaction::TRANSACTION_STATUS_INITIATE,
            Transaction::TRANSACTION_STATUS_INITIATED
        ];
        $canceledStatuses = [
            Transaction::TRANSACTION_OPERATION_CANCEL,
            Transaction::TRANSACTION_STATUS_AUTOCANCEL,
            Transaction::TRANSACTION_STATUS_CANCELLED
        ];

        if (in_array($transactionStatus, $initiatedStatuses)) {
            throw new LocalizedException(__('Your order was not approved in Vipps.'));
        }
        if (in_array($transactionStatus, $canceledStatuses)) {
            throw new LocalizedException(__('Your order was canceled in Vipps.'));
        }
    }
}
