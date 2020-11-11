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

namespace Vipps\Payment\Model\Quote;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Vipps\Payment\Gateway\Transaction\ShippingDetails;
use Vipps\Payment\Model\Helper\Utility;

/**
 * Class AddressUpdater
 * @package Vipps\Payment\Model\Quote
 */
class AddressUpdater
{
    /**
     * @var Utility
     */
    private $utility;

    /**
     * AddressUpdater constructor.
     * @param Utility $utility
     */
    public function __construct(Utility $utility)
    {
        $this->utility = $utility;
    }

    /**
     * Update quote addresses from source address.
     *
     * @param Quote|CartInterface $quote
     * @param Address|AddressInterface $sourceAddress
     * @throws \Exception
     */
    public function fromSourceAddress(Quote $quote, Address $sourceAddress)
    {
        $quote->setMayEditShippingAddress(false);
        $this->utility->disabledQuoteAddressValidation($quote);
        $this->updateQuoteAddresses($quote, $sourceAddress);
    }

    /**
     * Update quote addresses from source address.
     *
     * @param Quote $quote
     * @param Address $sourceAddress
     * @throws \Exception
     */
    private function updateQuoteAddresses(Quote $quote, Address $sourceAddress)
    {
        if (!$quote->getIsVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            $this->updateAddress($shippingAddress, $sourceAddress);
        }

        $billingAddress = $quote->getBillingAddress();
        $billingAddress->setSameAsBilling(false);
        $this->updateAddress($billingAddress, $sourceAddress);
    }

    /**
     * Update destination address from source.
     *
     * @param Address $destAddress
     * @param Address $sourceAddress
     * @throws \Exception
     */
    private function updateAddress(Address $destAddress, Address $sourceAddress)
    {
        $destAddress
            ->setStreet($sourceAddress->getStreet())
            ->setCity($sourceAddress->getCity())
            ->setCountryId(ShippingDetails::NORWEGIAN_COUNTRY_ID)
            ->setPostcode($sourceAddress->getPostcode())
            ->setSaveInAddressBook(false)
            ->setSameAsBilling(true)
            ->setCustomerAddressId(null)
            ->save();
    }
}
