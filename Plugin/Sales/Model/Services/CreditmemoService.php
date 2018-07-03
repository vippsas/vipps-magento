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
namespace Vipps\Payment\Plugin\Sales\Model\Services;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Service\CreditmemoService as CoreCreditmemoService;

/**
 * Class CreditmemoService
 * @package Vipps\Payment\Plugin\Sales\Model\Services
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class CreditmemoService
{
    /**
     * @param CoreCreditmemoService $creditmemoService
     * @param CreditmemoInterface $creditmemo
     * @param bool $offlineRequested
     * @return array
     * @throws LocalizedException
     */
    public function beforeRefund(CoreCreditmemoService $creditmemoService, CreditmemoInterface $creditmemo, $offlineRequested = false) //@codingStandardsIgnoreLine
    {
        $paymentMethod = $creditmemo->getOrder()->getPayment()->getMethod();
        if ($offlineRequested && $paymentMethod === 'vipps') {
            throw new LocalizedException(__('Vipps payment method does not support Refund Offline'));
        }

        return [$creditmemo, $offlineRequested];
    }
}
