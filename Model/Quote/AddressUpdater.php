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

namespace Vipps\Payment\Model\Quote;

use Magento\Braintree\Model\Paypal\Helper\AbstractHelper;

use Magento\Quote\{
    Api\CartRepositoryInterface, Api\Data\CartInterface, Model\Quote, Model\Quote\Address
};

use Vipps\Payment\Gateway\Transaction\ShippingDetails;

/**
 * Class AddressUpdater
 * @package Vipps\Payment\Model\Quote
 */
class AddressUpdater extends AbstractHelper
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * AddressUpdater constructor.
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    /**
     * Update quote addresses from source address.
     *
     * @param Quote $quote
     * @param Address $sourceAddress
     */
    public function fromSourceAddress(Quote $quote, Address $sourceAddress)
    {
        $quote->setMayEditShippingAddress(false);

        $this->updateQuoteAddresses($quote, $sourceAddress);
        $this->disabledQuoteAddressValidation($quote);

        /**
         * Unset shipping assignment to prevent from saving / applying outdated data
         * @see \Magento\Quote\Model\QuoteRepository\SaveHandler::processShippingAssignment
         */
        if ($quote->getExtensionAttributes()) {
            $quote->getExtensionAttributes()->setShippingAssignments(null);
        }
        $this->cartRepository->save($quote);
    }

    /**
     * @param Quote $quote
     * @param Address $address
     */
    private function updateQuoteAddresses(Quote $quote, Address $address)
    {
        if (!$quote->getIsVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            $this->updateAddress($shippingAddress, $address);
        }

        $billingAddress = $quote->getBillingAddress();
        $this->updateAddress($billingAddress, $address);
        $billingAddress->setSameAsBilling(false);
    }

    private function updateAddress(Address $destAddress, Address $sourceAddress)
    {
        $destAddress
            ->setStreet($sourceAddress->getStreet())
            ->setCity($sourceAddress->getCity())
            ->setCountryId(ShippingDetails::NORWEGIAN_COUNTRY_ID)
            ->setPostcode($sourceAddress->getPostcode())
            ->setSaveInAddressBook(false)
            ->setSameAsBilling(true)
            ->setCustomerAddressId(null);
    }
}