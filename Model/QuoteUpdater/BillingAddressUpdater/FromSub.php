<?php

declare(strict_types=1);

namespace Vipps\Payment\Model\QuoteUpdater\BillingAddressUpdater;

use Magento\Quote\Model\Quote;
use Vipps\Payment\Gateway\Command\CommandManager;
use Vipps\Payment\Gateway\Command\PaymentDetailsProvider;
use Vipps\Payment\Gateway\Transaction\Transaction;

class FromSub
{
    private PaymentDetailsProvider $paymentDetailsProvider;
    private CommandManager $commandManager;

    public function __construct(
        PaymentDetailsProvider $paymentDetailsProvider,
        CommandManager         $commandManager
    ) {
        $this->paymentDetailsProvider = $paymentDetailsProvider;
        $this->commandManager = $commandManager;
    }

    public function update(Quote $quote, Transaction $transaction)
    {
        if ($transaction->getSub()) {
            $userDetails = $this->commandManager->getUserInfo($transaction->getSub());
            $billingAddress = $quote->getBillingAddress();

            $billingAddress
                ->setStreet($userDetails['address']['street_address'] ?? '')
                ->setPostcode($userDetails['address']['postal_code'] ?? '')
                ->setCountryId($userDetails['address']['country'] ?? '')
                ->setRegionCode($userDetails['address']['region'] ?? '')
                ->setCity($userDetails['address']['region'] ?? '')
                ->setEmail($userDetails['email'] ?? '')
                ->setTelephone($userDetails['phone_number'] ?? '')
                ->setLastname($userDetails['family_name'] ?? '')
                ->setFirstname($userDetails['given_name'] ?? '')
                ->setSameAsBilling(false)
                ->setSaveInAddressBook(false)
                ->setCustomerAddressId(null);

        }
    }
}
