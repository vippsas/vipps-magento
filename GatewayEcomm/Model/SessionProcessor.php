<?php
/**
 * Copyright 2022 Vipps
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

namespace Vipps\Payment\GatewayEcomm\Model;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ResourceConnection;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Helper\Formatter;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Processor;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Psr\Log\LoggerInterface;
use Vipps\Checkout\Api\Data\QuoteInterface;
use Vipps\Checkout\Api\Data\QuoteStatusInterface;
use Vipps\Checkout\Gateway\Exception\WrongAmountException;
use Vipps\Checkout\Gateway\Data\Session;
use Vipps\Checkout\Model\Adminhtml\Config\Source\PaymentAction;
use Vipps\Checkout\Model\Exception\AcquireLockException;
use Vipps\Payment\Model\OrderLocator;
use Vipps\Payment\Model\QuoteLocator;

/**
 * Class SessionProcessor
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SessionProcessor
{
    use Formatter;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var QuoteLocator
     */
    private $quoteLocator;

    /**
     * @var OrderLocator
     */
    private $orderLocator;

    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var QuoteUpdater
     */
    private $quoteUpdater;

    /**
     * @var LockManager
     */
    private $lockManager;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var QuoteManagement
     */
    private $quoteManagement;

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var SessionManager
     */
    private $sessionManager;

    /**
     * @var ReceiptSender
     */
    private $receiptSender;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * SessionProcessor constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param CartRepositoryInterface $cartRepository
     * @param CartManagementInterface $cartManagement
     * @param QuoteLocator $quoteLocator
     * @param OrderLocator $orderLocator
     * @param Processor $processor
     * @param QuoteUpdater $quoteUpdater
     * @param LockManager $lockManager
     * @param ConfigInterface $config
     * @param QuoteManagement $quoteManagement
     * @param OrderManagementInterface $orderManagement
     * @param SessionManager $sessionManager
     * @param ReceiptSender $receiptSender
     * @param LoggerInterface $logger
     * @param ResourceConnection $resourceConnection
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface $cartRepository,
        CartManagementInterface $cartManagement,
        QuoteLocator $quoteLocator,
        OrderLocator $orderLocator,
        Processor $processor,
        QuoteUpdater $quoteUpdater,
        LockManager $lockManager,
        ConfigInterface $config,
        QuoteManagement $quoteManagement,
        OrderManagementInterface $orderManagement,
        SessionManager $sessionManager,
        ReceiptSender $receiptSender,
        LoggerInterface $logger,
        ResourceConnection $resourceConnection
    ) {
        $this->orderRepository = $orderRepository;
        $this->cartRepository = $cartRepository;
        $this->cartManagement = $cartManagement;
        $this->quoteLocator = $quoteLocator;
        $this->orderLocator = $orderLocator;
        $this->processor = $processor;
        $this->quoteUpdater = $quoteUpdater;
        $this->lockManager = $lockManager;
        $this->config = $config;
        $this->quoteManagement = $quoteManagement;
        $this->orderManagement = $orderManagement;
        $this->sessionManager = $sessionManager;
        $this->receiptSender = $receiptSender;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param QuoteInterface $vippsQuote
     *
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws AcquireLockException
     * @throws \Exception
     */
    public function process(QuoteInterface $vippsQuote)
    {
        try {
            $lockName = $this->acquireLock($vippsQuote->getReservedOrderId());

            $session = $this->sessionManager->getSession(
                $vippsQuote->getCheckoutSessionId()
            );

            $diff = (new \DateTime())->diff((new \DateTime($vippsQuote->getCreatedAt())));
            $daysPassed = $diff->days;

            if ($session->getPaymentDetails()->isAuthorised()) {
                $this->processAuthorisedSession($vippsQuote, $session);
            } elseif ($daysPassed > 1
                || $session->isSessionExpired()
                || $session->getPaymentDetails()->isTerminated()
            ) {
                $this->processTerminatedSession($vippsQuote);
            }

            return $session;
        } finally {
            if (isset($lockName)) {
                $this->releaseLock($lockName);
            }
        }
    }

    /**
     * @param QuoteInterface $vippsQuote
     *
     * @throws CouldNotSaveException
     * @throws \Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function processTerminatedSession(QuoteInterface $vippsQuote)
    {
        $order = $this->orderLocator->get($vippsQuote->getReservedOrderId());
        if ($order) {
            $this->cancelOrder($order);
        }
        $vippsQuote->setStatus(QuoteStatusInterface::STATUS_CANCELED);
        $this->quoteManagement->save($vippsQuote);
    }

    /**
     * @param QuoteInterface $vippsQuote
     * @param Session $session
     *
     * @return OrderInterface|null
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function processAuthorisedSession(QuoteInterface $vippsQuote, Session $session)
    {
        $order = $this->orderLocator->get($vippsQuote->getReservedOrderId());
        if (!$order) {
            $order = $this->placeOrder($vippsQuote, $session);
        }

        $this->sendReceipt($order);

        $paymentAction = $this->config->getValue('vipps_payment_action');
        $this->processAction($paymentAction, $order, $session);

        $this->notify($order);

        $vippsQuote->setStatus(QuoteInterface::STATUS_RESERVED);
        $this->quoteManagement->save($vippsQuote);

        return $order;
    }

    /**
     * @param string|null $action
     * @param OrderInterface $order
     * @param Session $session
     *
     * @throws LocalizedException
     */
    private function processAction($action, OrderInterface $order, Session $session)
    {
        switch ($action) {
            case PaymentAction::ACTION_AUTHORIZE_CAPTURE:
                $this->capture($order);
                break;
            default:
                $this->authorize($order, $session);
        }
    }

    /**
     * @param OrderInterface $order
     *
     * @throws \Vipps\Checkout\Gateway\Exception\VippsException
     */
    private function sendReceipt(OrderInterface $order)
    {
        if (!in_array($order->getState(), [Order::STATE_NEW, Order::STATE_PAYMENT_REVIEW])) {
            return;
        }

        $this->receiptSender->send($order);
    }

    /**
     * @param $reservedOrderId
     *
     * @return string
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws AcquireLockException
     * @throws \Exception
     */
    private function acquireLock($reservedOrderId)
    {
        $lockName = 'vipps_place_order_' . $reservedOrderId;
        $retries = 0;
        $canLock = $this->lockManager->lock($lockName, 10);

        while (!$canLock && ($retries < 10)) {
            usleep(200000);
            //wait for 0.2 seconds
            $retries++;
            $canLock = $this->lockManager->lock($lockName, 10);
        }

        if (!$canLock) {
            throw new AcquireLockException(
                __('Can not acquire lock for order "%1"', $reservedOrderId)
            );
        }

        return $lockName;
    }

    /**
     * @param CartInterface|Quote $quote
     * @param Session $session
     *
     * @return OrderInterface|null
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    private function placeOrder(QuoteInterface $vippsQuote, Session $session)
    {
        $quote = $this->cartRepository->get($vippsQuote->getQuoteId());
        if (!$quote) {
            throw new \Exception( //@codingStandardsIgnoreLine
                __('Could not place order. Could not find quote.')
            );
        }

        if ($vippsQuote->getReservedOrderId()
            && $quote->getReservedOrderId() !== $vippsQuote->getReservedOrderId()
        ) {
            $quote->setReservedOrderId($vippsQuote->getReservedOrderId());
            $this->cartRepository->save($quote);
        }

        if (!$quote->getReservedOrderId() || $quote->getReservedOrderId() !== $session->getReference()) {
            throw new \Exception( //@codingStandardsIgnoreLine
                __('Quote reserved order id does not match Vipps transaction order id.')
            );
        }

        $this->quoteUpdater->execute($quote, $session);

        $quote = $this->cartRepository->get($quote->getId());

        // fix quote to be able to work from different areas (frontend/adminhtml/etc...)
        $this->fixQuote($quote);

        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();

        $this->validateAmount($quote, $session);

        // set quote active, collect totals and place order
        $quote->setIsActive(true);
        $orderId = $this->cartManagement->placeOrder($quote->getId());

        $order = $this->orderRepository->get($orderId);

        $quote->setReservedOrderId(null);
        $quote->setIsActive(false);
        $this->cartRepository->save($quote);

        return $order;
    }

    /**
     * @param CartInterface|Quote $quote
     */
    private function fixQuote($quote)
    {
        $websiteId = $quote->getStore()->getWebsiteId();
        foreach ($quote->getAllItems() as $item) {
            /** @var Quote\Item $item */
            $item->getProduct()->setWebsiteId($websiteId);
        }
    }

    /**
     * Check if reserved Order amount in vipps is the same as in Magento.
     *
     * @param CartInterface $quote
     * @param Session $session
     *
     * @return void
     * @throws WrongAmountException
     */
    private function validateAmount(CartInterface $quote, Session $session)
    {
        $quoteAmount = (int)round($this->formatPrice($quote->getGrandTotal()) * 100);
        $vippsAmount = (int)$session->getPaymentDetails()->getAmount()->getValue();

        if ($quoteAmount !== $vippsAmount) {
            throw new WrongAmountException(
                __("Quote Grand Total {$quoteAmount} does not match Transaction Amount {$vippsAmount}")
            );
        }
    }

    /**
     * Capture
     *
     * @param OrderInterface $order
     *
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function capture(OrderInterface $order)
    {
        if (!in_array($order->getState(), [Order::STATE_NEW, Order::STATE_PAYMENT_REVIEW])) {
            return;
        }

        // preconditions
        $totalDue = $order->getTotalDue();
        $baseTotalDue = $order->getBaseTotalDue();

        /** @var Payment $payment */
        $payment = $order->getPayment();
        $payment->setAmountAuthorized($totalDue);
        $payment->setBaseAmountAuthorized($baseTotalDue);

        // do capture
        $this->processor->capture($payment, null);
        $this->orderRepository->save($order);
    }

    /**
     * Authorize action
     *
     * @param OrderInterface $order
     * @param Session $session
     */
    private function authorize(OrderInterface $order, Session $session)
    {
        if (!in_array($order->getState(), [Order::STATE_NEW, Order::STATE_PAYMENT_REVIEW])) {
            return;
        }

        // preconditions
        $totalDue = $order->getTotalDue();
        $baseTotalDue = $order->getBaseTotalDue();

        $payment = $order->getPayment();
        if ($payment instanceof Payment) {
            $transactionId = $session->getSessionId();
            $payment->setIsTransactionClosed(false);
            $payment->setTransactionId($transactionId);
            $payment->setTransactionAdditionalInfo(
                PaymentTransaction::RAW_DETAILS,
                $session->getPaymentDetails()->getAggregate()->getData()
            );
        }

        // do authorize
        $this->processor->authorize($payment, false, $baseTotalDue);
        // base amount will be set inside
        $payment->setAmountAuthorized($totalDue);
        $this->orderRepository->save($order);
    }

    /**
     * @param $lockName
     *
     * @return bool
     * @throws InputException
     */
    private function releaseLock($lockName)
    {
        return $this->lockManager->unlock($lockName);
    }

    /**
     * @param OrderInterface $order
     */
    private function notify(OrderInterface $order)
    {
        if (!$order->getEmailSent()) {
            $this->orderManagement->notify($order->getEntityId());
        }
    }

    /**
     * @param $order
     *
     * @throws \Exception
     */
    private function cancelOrder($order): void
    {
        if ($order->getState() === Order::STATE_NEW) {
            $this->orderManagement->cancel($order->getEntityId());
        } elseif ($order->getState() === Order::STATE_PAYMENT_REVIEW) {
            $connection = $this->resourceConnection->getConnection();
            try {
                $connection->beginTransaction();

                $order->setState(Order::STATE_NEW);
                $this->orderRepository->save($order);
                $this->orderManagement->cancel($order->getEntityId());

                $connection->commit();
            } catch (\Exception $e) {
                $connection->rollBack();
                throw $e;
            }
        }
    }
}
