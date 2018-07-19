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
namespace Vipps\Payment\Model;

use Magento\Quote\{Api\CartRepositoryInterface, Model\Quote, Model\Quote\Address};
use Magento\Braintree\Model\Paypal\Helper\AbstractHelper;
use Vipps\Payment\Gateway\Transaction\{ShippingDetails, Transaction};

/**
 * Class QuoteUpdater
 * @package Vipps\Payment\Model\Helper
 */
class QuoteUpdater extends AbstractHelper
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * QuoteUpdater constructor.
     *
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        CartRepositoryInterface $cartRepository
    ) {
        $this->cartRepository = $cartRepository;
    }

    /**
     * Method to update Quote data.
     *
     * @param Quote $quote
     * @param Transaction $transaction
     */
    public function execute(Quote $quote, Transaction $transaction)
    {
        if ($transaction->isExpressCheckout()) {
            $payment = $quote->getPayment();
            $payment->setMethod('vipps');

            $quote->setMayEditShippingAddress(false);
            $quote->setMayEditShippingMethod(true);

            $this->updateQuoteAddress($quote, $transaction);
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
    }

    /**
     * @param Quote $quote
     * @param Transaction $transaction
     */
    private function updateQuoteAddress(Quote $quote, Transaction $transaction)
    {
        if (!$quote->getIsVirtual()) {
            $this->updateShippingAddress($quote, $transaction);
        }

        $this->updateBillingAddress($quote, $transaction);
    }

    /**
     * @param Quote $quote
     * @param Transaction $transaction
     */
    private function updateShippingAddress(Quote $quote, Transaction $transaction)
    {
        $userDetails = $transaction->getUserDetails();
        $shippingDetails = $transaction->getShippingDetails();
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setLastname($userDetails->getLastName());
        $shippingAddress->setFirstname($userDetails->getFirstName());
        $shippingAddress->setEmail($userDetails->getEmail());
        $shippingAddress->setTelephone($userDetails->getMobileNumber());
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->setShippingMethod($shippingDetails->getShippingMethodId());
        $shippingAddress->setShippingAmount($shippingDetails->getShippingCost());
        $this->updateAddressData($shippingAddress, $shippingDetails);

        //We do not save user address from vipps in Magento
        $shippingAddress->setSaveInAddressBook(false);
        $shippingAddress->setSameAsBilling(true);
        $shippingAddress->unsCustomerAddressId();
    }

    /**
     * @param Quote $quote
     * @param Transaction $transaction
     */
    private function updateBillingAddress(Quote $quote, Transaction $transaction)
    {
        $userDetails = $transaction->getUserDetails();
        $billingAddress = $quote->getBillingAddress();
        $this->updateAddressData($billingAddress, $transaction->getShippingDetails());

        $billingAddress->setLastname($userDetails->getLastName());
        $billingAddress->setFirstname($userDetails->getFirstName());
        $billingAddress->setEmail($userDetails->getEmail());
        $billingAddress->setTelephone($userDetails->getMobileNumber());
        //We do not save user address from vipps in Magento
        $billingAddress->setSaveInAddressBook(false);
        $billingAddress->setSameAsBilling(false);
        $billingAddress->unsCustomerAddressId();
    }

    /**
     * @param Address $address
     * @param ShippingDetails $shippingDetails
     */
    private function updateAddressData(Address $address, ShippingDetails $shippingDetails)
    {
        $address->setStreet($shippingDetails->getStreet());
        $address->setCity($shippingDetails->getCity());
        $address->setCountryId(ShippingDetails::NORWEGIAN_COUNTRY_ID);
        $address->setPostcode($shippingDetails->getPostCode());

        $address->setSaveInAddressBook(false);
        $address->setSameAsBilling(true);
        $address->setCustomerAddressId(null);
    }
}
