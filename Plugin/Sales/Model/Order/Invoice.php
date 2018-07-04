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
namespace Vipps\Payment\Plugin\Sales\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Invoice as CoreInvoice;

/**
 * Class Invoice
 * @package Vipps\Payment\Plugin\Sales\Model\Order
 */
class Invoice
{
    /**
     * Before Invoice register plugin.
     *
     * Plugin prevents creating invoice with 'Capture Offline' for orders with vipps payment method
     *
     * @param CoreInvoice $subject
     * @throws LocalizedException
     */
    public function beforeRegister(CoreInvoice $subject)
    {
        $captureCase = $subject->getRequestedCaptureCase();
        $paymentMethod = $subject->getOrder()->getPayment()->getMethod();

        if (CoreInvoice::CAPTURE_OFFLINE === $captureCase && $paymentMethod === 'vipps') {
            throw new LocalizedException(__('Vipps payment method does not support Capture Offline'));
        }
    }
}
