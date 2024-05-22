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

namespace Vipps\Payment\Model;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Vipps\Payment\Gateway\Command\PaymentDetailsProvider;
use Vipps\Payment\Gateway\Exception\VippsException;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\Gateway\Transaction\TransactionBuilder;
use Vipps\Payment\Model\Helper\Utility;
use Vipps\Payment\Model\QuoteUpdater\BillingAddressUpdater;

/**
 * Class QuoteUpdater
 * @package Vipps\Payment\Model\Helper
 */
class QuoteUpdater
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var PaymentDetailsProvider
     */
    private $paymentDetailsProvider;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;
    /**
     * @var Utility
     */
    private $utility;
    private BillingAddressUpdater $billingAddressUpdater;

    /**
     * QuoteUpdater constructor.
     *
     * @param CartRepositoryInterface $cartRepository
     * @param PaymentDetailsProvider $paymentDetailsProvider
     * @param TransactionBuilder $transactionBuilder
     * @param Utility $utility
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        PaymentDetailsProvider $paymentDetailsProvider,
        TransactionBuilder $transactionBuilder,
        BillingAddressUpdater $billingAddressUpdater,
        Utility $utility
    ) {
        $this->cartRepository = $cartRepository;
        $this->paymentDetailsProvider = $paymentDetailsProvider;
        $this->transactionBuilder = $transactionBuilder;
        $this->utility = $utility;
        $this->billingAddressUpdater = $billingAddressUpdater;
    }

    /**
     * @param CartInterface $quote
     * @param Transaction $transaction
     *
     * @return bool|CartInterface|Quote
     */
    public function execute(CartInterface $quote, Transaction $transaction)
    {
        /** @var Quote $quote */
        $quote->setMayEditShippingAddress(false);
        $quote->setMayEditShippingMethod(true);

        $this->updateQuoteAddresses($quote, $transaction);
        $this->utility->disabledQuoteAddressValidation($quote);

        /**
         * Unset shipping assignment to prevent from saving / applying outdated data
         * @see \Magento\Quote\Model\QuoteRepository\SaveHandler::processShippingAssignment
         */
        if ($quote->getExtensionAttributes()) {
            $quote->getExtensionAttributes()->setShippingAssignments(null);
        }
        $this->cartRepository->save($quote);
        return $quote;
    }

    /**
     * @param Quote $quote
     * @param Transaction $transaction
     */
    private function updateQuoteAddresses(Quote $quote, Transaction $transaction)
    {
        $this->billingAddressUpdater->update($quote, $transaction);
        if (!$quote->getIsVirtual()) {
            $this->updateShippingAddress($quote, $transaction);
        }
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
        $shippingAddress->setShippingMethod($shippingDetails->getShippingMethodId());
        $shippingAddress->setShippingAmount($shippingDetails->getShippingCost());

        // try to obtain postCode one more time if it is not done before
        if (!$shippingAddress->getPostcode() && $shippingDetails->getPostcode()) {
            $shippingAddress->setPostcode($shippingDetails->getPostcode());
        }

        $shippingAddress->setSameAsBilling(true);

        //We do not save user address from vipps in Magento
        $shippingAddress->setSaveInAddressBook(false);
        $shippingAddress->setCustomerAddressId(null);
    }
}
