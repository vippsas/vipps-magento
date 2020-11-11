<?php
/**
 * Copyright 2020 Vipps
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

namespace Vipps\Payment\Model\Order\PartialVoid;

use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class SendMail
 * @package Vipps\Payment\Model\Order\PartialVoid
 */
class SendMail
{
    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * SendMail constructor.
     *
     * @param TransportBuilder $transportBuilder
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(TransportBuilder $transportBuilder, StoreManagerInterface $storeManager, Config $config)
    {
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->config = $config;
    }

    /**
     * @param OrderInterface $order
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\MailException
     */
    public function send(OrderInterface $order)
    {
        $transport = $this->transportBuilder
            ->setTemplateIdentifier($this->config->getEmailTemplate($order->getStoreId()))
            ->setFromByScope($this->config->emailSender($order->getStoreId()))
            ->addTo($order->getCustomerEmail(), "{$order->getCustomerFirstname()} {$order->getCustomerLastname()}")
            ->setTemplateOptions([
                'area'  => Area::AREA_FRONTEND,
                'store' => $order->getStoreId(),
            ])
            ->setTemplateVars([
                'order'         => $order,
                'payment_html'  => $order->getPayment()->getMethodInstance()->getTitle(),
                'email_message' => $this->config->getEmailMessage($order->getStoreId()),
            ])
            ->getTransport();

        $transport->sendMessage();
    }
}
