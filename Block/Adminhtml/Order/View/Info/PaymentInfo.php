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

namespace Vipps\Payment\Block\Adminhtml\Order\View\Info;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;

/**
 * Class PaymentInfo
 * @package Vipps\Payment\Block\Adminhtml\Order\View\Info
 */
class PaymentInfo extends Template
{
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
    }

    /**
     * Return payment method model
     *
     * @return string
     */
    public function getPaymentType(): string
    {
        $paymentMethod = $this->getPaymentMethod();
        $paymentTypes = [
            'regular_checkout' => __('Regular'),
            'express_checkout' => __('Express'),
        ];

        return (string) $paymentTypes[$paymentMethod] ?? '';
    }

    /**
     *
     *
     * @return string
     */
    private function getPaymentMethod(): string
    {
        $order = $this->registry->registry('current_order');
        if (!$order) {
            return [];
        }
        $payment = $order->getPayment();
        if (!$payment) {
            return [];
        }
        $addInfo = (array) $payment->getAdditionalInformation();

        return (string) $addInfo['method_type'] ?? '';
    }
}
