<?php

declare(strict_types=1);

namespace Vipps\Payment\Model\QuoteUpdater\BillingAddressUpdater;

use Magento\Quote\Model\Quote;
use Vipps\Payment\Gateway\Transaction\Transaction;

class FromTransaction
{
    public function update(Quote $quote, Transaction $transaction)
    {
        $userDetails = $transaction->getUserDetails();
        $billingAddress = $quote->getBillingAddress();
        $shippingDetails = $transaction->getShippingDetails();

        $billingAddress->setLastname($userDetails->getLastName());
        $billingAddress->setFirstname($userDetails->getFirstName());
        $billingAddress->setEmail($userDetails->getEmail());
        $billingAddress->setTelephone($userDetails->getMobileNumber());

        // try to obtain postCode one more time if it is not done before
        if (!$billingAddress->getPostcode() && $shippingDetails->getPostcode()) {
            $billingAddress->setPostcode($shippingDetails->getPostcode());
        }

        $billingAddress->setSameAsBilling(false);

        //We do not save user address from vipps in Magento
        $billingAddress->setSaveInAddressBook(false);
        $billingAddress->setCustomerAddressId(null);
    }
}
