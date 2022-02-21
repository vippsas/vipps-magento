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
namespace Vipps\Payment\Gateway\Request\Initiate;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Data\Quote\QuoteAdapter;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Payment;
use Vipps\Payment\Gateway\Request\SubjectReader;

/**
 * Class OrderIdBuilder
 * @package Vipps\Payment\Gateway\Request\Initiate
 */
class OrderIdBuilder implements InitiateBuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * MerchantDataBuilder constructor.
     *
     * @param SubjectReader $subjectReader
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        SubjectReader $subjectReader,
        CartRepositoryInterface $cartRepository
    ) {
        $this->subjectReader = $subjectReader;
        $this->cartRepository = $cartRepository;
    }

    /**
     * Get merchant related data for Initiate payment request.
     *
     * @param array $buildSubject
     *
     * @return array
     * @throws \Exception
     */
    public function build(array $buildSubject)
    {
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        /** @var Payment $payment */
        $orderAdapter = $paymentDO->getOrder();

        if (!$orderAdapter->getOrderIncrementId() && $orderAdapter instanceof QuoteAdapter) {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->cartRepository->get($orderAdapter->getId());
            $quote->reserveOrderId();
        }

        return [];
    }
}
