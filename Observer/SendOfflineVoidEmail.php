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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Sales\Model\Order;
use Vipps\Payment\Model\Order\PartialVoid\SendMail;
use Vipps\Payment\Model\Order\PartialVoid\Config as PartialVoidConfig;

/**
 * Class SendOfflineVoidEmail
 * @package Vipps\Payment\Observer
 */
class SendOfflineVoidEmail implements ObserverInterface
{
    /**
     * @var SendMail
     */
    private $sendMail;

    /**
     * @var PartialVoidConfig
     */
    private $config;

    /**
     * SendOfflineVoidEmail constructor.
     *
     * @param SendMail $sendMail
     * @param PartialVoidConfig $config
     */
    public function __construct(
        SendMail $sendMail,
        PartialVoidConfig $config
    ) {
        $this->sendMail = $sendMail;
        $this->config = $config;
    }

    /**
     * Send email to customer about offline void
     *
     * @param Observer $observer
     *
     * @throws LocalizedException
     * @throws MailException
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getData('order');
        $payment = $order->getPayment();

        $offlineVoidEnabled = $this->config->isOfflinePartialVoidEnabled($order->getStoreId());
        $sendMailEnabled = $this->config->isSendMailEnabled($order->getStoreId());

        if ($payment->getMethod() === 'vipps'
            && $offlineVoidEnabled
            && $sendMailEnabled
            && $order->getTotalDue() > 0
        ) {
            $this->sendMail->send($order);
        }
    }
}
