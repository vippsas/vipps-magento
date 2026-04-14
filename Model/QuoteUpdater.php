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
use Vipps\Payment\GatewayEpayment\Command\PaymentDetailsProvider;
use Vipps\Payment\GatewayEpayment\Exception\VippsException;
use Vipps\Payment\Model\Helper\Utility;
use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\GatewayEpayment\Data\PaymentBuilder;

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
     * @var Utility
     */
    private $utility;

    /**
     * QuoteUpdater constructor.
     *
     * @param CartRepositoryInterface $cartRepository
     * @param PaymentDetailsProvider $paymentDetailsProvider
     * @param Utility $utility
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        PaymentDetailsProvider $paymentDetailsProvider,
        Utility $utility
    ) {
        $this->cartRepository = $cartRepository;
        $this->paymentDetailsProvider = $paymentDetailsProvider;
        $this->utility = $utility;
    }

    /**
     * @param CartInterface $quote
     * @param Payment $transaction
     *
     * @return bool|CartInterface|Quote
     */
    public function execute(CartInterface $quote, Payment $transaction)
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
     * @param Payment $transaction
     */
    private function updateQuoteAddresses(Quote $quote, Payment $transaction)
    {
        $this->updateBillingAddress($quote, $transaction);
        if (!$quote->getIsVirtual()) {
            $this->updateShippingAddress($quote, $transaction);
        }
    }

    /**
     * @param Quote $quote
     * @param Payment $transaction
     */
    private function updateShippingAddress(Quote $quote, Payment $transaction)
    {
        $userDetails = $transaction->getUserDetails();
        $shippingDetails = $transaction->getShippingDetails();
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setLastname($userDetails->getLastName());
        $shippingAddress->setFirstname($userDetails->getFirstName());
        $shippingAddress->setEmail($userDetails->getEmail());
        $shippingAddress->setTelephone($userDetails->getMobileNumber());
        $shippingAddress->setShippingMethod($shippingDetails->getShippingOptionId());
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

    /**
     * @param Quote $quote
     * @param Payment $transaction
     */
    private function updateBillingAddress(Quote $quote, Payment $transaction)
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
