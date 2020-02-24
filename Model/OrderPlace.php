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

use Magento\Framework\Exception\{AlreadyExistsException, CouldNotSaveException, InputException, NoSuchEntityException};
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Helper\Formatter;
use Magento\Quote\Api\{CartManagementInterface, CartRepositoryInterface, Data\CartInterface};
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\{Data\OrderInterface, OrderManagementInterface, OrderRepositoryInterface};
use Magento\Sales\Model\{Order,
    Order\Payment,
    Order\Payment\Processor,
    Order\Payment\Transaction as PaymentTransaction};
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Api\Data\QuoteStatusInterface;
use Vipps\Payment\Gateway\{Exception\VippsException, Transaction\Transaction};
use Vipps\Payment\Gateway\Exception\WrongAmountException;
use Vipps\Payment\Model\Adminhtml\Source\PaymentAction;

/**
 * Class OrderManagement
 * @package Vipps\Payment\Model
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderPlace
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
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var OrderLocator
     */
    private $orderLocator;

    /**
     * @var QuoteLocator
     */
    private $quoteLocator;

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
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var QuoteManagement
     */
    private $quoteManagement;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * OrderPlace constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param CartRepositoryInterface $cartRepository
     * @param OrderManagementInterface $orderManagement
     * @param CartManagementInterface $cartManagement
     * @param OrderLocator $orderLocator
     * @param QuoteLocator $quoteLocator
     * @param Processor $processor
     * @param QuoteUpdater $quoteUpdater
     * @param LockManager $lockManager
     * @param ConfigInterface $config
     * @param CommandManagerInterface $commandManager
     * @param QuoteManagement $quoteManagement
     * @param LoggerInterface $logger
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface $cartRepository,
        OrderManagementInterface $orderManagement,
        CartManagementInterface $cartManagement,
        OrderLocator $orderLocator,
        QuoteLocator $quoteLocator,
        Processor $processor,
        QuoteUpdater $quoteUpdater,
        LockManager $lockManager,
        ConfigInterface $config,
        CommandManagerInterface $commandManager,
        QuoteManagement $quoteManagement,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->cartRepository = $cartRepository;
        $this->orderManagement = $orderManagement;
        $this->cartManagement = $cartManagement;
        $this->orderLocator = $orderLocator;
        $this->quoteLocator = $quoteLocator;
        $this->processor = $processor;
        $this->quoteUpdater = $quoteUpdater;
        $this->lockManager = $lockManager;
        $this->config = $config;
        $this->commandManager = $commandManager;
        $this->quoteManagement = $quoteManagement;
        $this->logger = $logger;
    }

    /**
     * @param CartInterface $quote
     * @param Transaction $transaction
     *
     * @return OrderInterface|null
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws VippsException
     * @throws WrongAmountException
     */
    public function execute(CartInterface $quote, Transaction $transaction)
    {
        $order = $this->orderLocator->get($transaction->getOrderId());
        if ($order) {
            return $order;
        }

        if (!$this->canPlaceOrder($quote, $transaction)) {
            return null;
        }

        $lockName = $this->acquireLock($quote);
        if ($lockName) {
            try {
                $order = $this->placeOrder($quote, $transaction);
                if ($order) {
                    $this->updateVippsQuote($quote);
                    $this->notify($order);
                }
                return $order;
            } finally {
                $this->releaseLock($lockName);
            }
        }

        return null;
    }

    /**
     * Check can we place order or not based on transaction object
     *
     * @param CartInterface $cart
     * @param Transaction $transaction
     *
     * @return bool
     */
    private function canPlaceOrder(CartInterface $cart, Transaction $transaction)
    {
        if (!$transaction->isTransactionReserved()) {
            return false;
        }

        if (!$cart->getReservedOrderId() || $cart->getReservedOrderId() !== $transaction->getOrderId()) {
            return false;
        }

        return true;
    }

    /**
     * @param CartInterface $quote
     *
     * @return bool|string
     * @throws AlreadyExistsException
     * @throws InputException
     */
    private function acquireLock(CartInterface $quote)
    {
        $reservedOrderId = $quote->getReservedOrderId();
        if ($reservedOrderId) {
            $lockName = 'vipps_place_order_' . $reservedOrderId;
            if ($this->lockManager->lock($lockName, 10)) {
                return $lockName;
            }
        }
        return false;
    }

    /**
     * @param CartInterface $quote
     * @param Transaction $transaction
     *
     * @return OrderInterface|null
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws VippsException
     * @throws WrongAmountException
     */
    private function placeOrder(CartInterface $quote, Transaction $transaction)
    {
        /** @var Quote $clonedQuote */
        $clonedQuote = clone $quote;

        if ($transaction->isExpressCheckout()) {
            $this->quoteUpdater->execute($clonedQuote, $transaction);
        }

        $clonedQuote = $this->cartRepository->get($clonedQuote->getId());

        // fix quote to be able to work from different areas (frontend/adminhtml/etc...)
        $this->fixQuote($clonedQuote);

        $clonedQuote->getShippingAddress()->setCollectShippingRates(true);
        $clonedQuote->collectTotals();

        $this->validateAmount($clonedQuote, $transaction);

        // set quote active, collect totals and place order
        $clonedQuote->setIsActive(true);
        $orderId = $this->cartManagement->placeOrder($clonedQuote->getId());
        $order = $this->orderRepository->get($orderId);

        $clonedQuote->setReservedOrderId(null);
        $this->cartRepository->save($clonedQuote);

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
     * @param Transaction $transaction
     *
     * @return void
     * @throws WrongAmountException
     */
    private function validateAmount(CartInterface $quote, Transaction $transaction)
    {
        $quoteAmount = (int)round($this->formatPrice($quote->getGrandTotal()) * 100);
        $vippsAmount = (int)$transaction->getTransactionSummary()->getRemainingAmountToCapture();

        if ($quoteAmount != $vippsAmount) {
            throw new WrongAmountException(
                __("Quote Grand Total {$quoteAmount} does not match Transaction Amount {$vippsAmount}")
            );
        }
    }

    /**
     * Update vipps quote with success.
     *
     * @param CartInterface $cart
     */
    private function updateVippsQuote(CartInterface $cart)
    {
        try {
            $vippsQuote = $this->quoteManagement->getByQuote($cart);
            $vippsQuote->setStatus(QuoteStatusInterface::STATUS_PLACED);
            $this->quoteManagement->save($vippsQuote);
        } catch (\Throwable $e) {
            // Order is submitted but failed to update Vipps Quote. It should not affect order flow.
            $this->logger->error($e->getMessage());
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
     * @param $lockName
     *
     * @return bool
     * @throws InputException
     */
    private function releaseLock($lockName)
    {
        return $this->lockManager->unlock($lockName);
    }
}
