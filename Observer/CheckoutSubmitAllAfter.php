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
declare(strict_types=1);

namespace Vipps\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Model\QuoteRepository;

/**
 * Class CheckoutSubmitAllAfter
 * @package Vipps\Payment\Observer
 */
class CheckoutSubmitAllAfter implements ObserverInterface
{
    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * CheckoutSubmitAllAfter constructor.
     *
     * @param OrderManagementInterface $orderManagement
     * @param QuoteRepository $quoteRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderManagementInterface $orderManagement,
        QuoteRepository $quoteRepository,
        LoggerInterface $logger
    ) {
        $this->orderManagement = $orderManagement;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
    }

    /**
     * Marks vipps quote as placed after order placed
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var OrderInterface $order */
        $order = $observer->getData('order');
        if ('vipps' == $order->getPayment()->getMethod()) {
            try {
                // send order placed email
                $this->notify($order);

                // updated vipps quote
                $vippsQuote = $this->quoteRepository->loadByOrderId($order->getIncrementId());
                $vippsQuote->setOrderId((int)$order->getEntityId());
                $vippsQuote->setStatus(QuoteInterface::STATUS_PENDING);
                $this->quoteRepository->save($vippsQuote);
            } catch (\Throwable $t) {
                $this->logger->error($t->getMessage());
            }
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
}
