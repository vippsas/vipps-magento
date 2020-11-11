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
namespace Vipps\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Vipps\Payment\Model\Quote\ShippingMethodValidator;

/**
 * Verify The Vipps payment is available for chosen shipping method.
 */
class AvailabilityByShippingMethod implements ObserverInterface
{
    /**
     * @var ShippingMethodValidator
     */
    private $shippingMethodValidator;

    /**
     * AvailabilityByShippingMethod constructor.
     * @param ShippingMethodValidator $shippingMethodValidator
     */
    public function __construct(ShippingMethodValidator $shippingMethodValidator)
    {
        $this->shippingMethodValidator = $shippingMethodValidator;
    }

    /**
     * @param Observer $observer
     * @return null
     *
     * 'result' => $checkResult,
     * 'method_instance' => $this,
     * 'quote' => $quote
     *
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $method = $observer->getData('method_instance');
        $quote = $observer->getData('quote');
        $result = $observer->getData('result');

        // Ignore not Vipps method and in case no quote here.
        if ($method->getCode() !== 'vipps' || !$quote) {
            return;
        }

        /** @var Quote $quote */
        $quote = $observer->getData('quote');
        if (!$this->shippingMethodValidator->isValid($quote->getShippingAddress()->getShippingMethod())) {
            $result->setData('is_available', false);
        }

        return null;
    }
}
