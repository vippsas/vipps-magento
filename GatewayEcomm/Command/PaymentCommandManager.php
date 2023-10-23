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
namespace Vipps\Payment\GatewayEcomm\Command;

use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Vipps\Payment\Api\Payment\CommandManagerInterface;

/**
 * Class CommandManager
 * @package Vipps\Payment\Model
 */
class PaymentCommandManager extends CommandManager implements CommandManagerInterface
{
    public function getPayment($reference, $arguments = [])
    {
        $arguments['reference'] = $reference;

        return $this->executeByCode('get-payment', null, $arguments);
    }

    public function getPaymentEventLog($orderId, $arguments = [])
    {
        $arguments['order_id'] = $orderId;

        return $this->executeByCode('get-payment-event-log', null, $arguments);
    }

    /**
     * @param OrderInterface $order
     * @param array $arguments
     *
     * @return mixed|void
     */
    public function sendReceipt(OrderInterface $order, $arguments = [])
    {
        $arguments['order'] = $order;

        return $this->executeByCode('send-receipt', null, $arguments);
    }

    /**
     * {@inheritdoc}
     *
     * @param InfoInterface $payment
     * @param array $arguments
     *
     * @return ResultInterface|mixed|null
     * @throws CommandException
     * @throws NotFoundException
     */
    public function cancel(InfoInterface $payment, $arguments = [])
    {
        return $this->executeByCode('cancel', $payment, $arguments);
    }

    public function initiatePayment(InfoInterface $payment, $arguments)
    {
        $quote = $payment->getQuote();
        $quote->setReservedOrderId(null);

        return $this->executeByCode('initiate', $payment, $arguments);
    }
}
