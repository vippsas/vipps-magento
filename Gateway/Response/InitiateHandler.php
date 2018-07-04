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
namespace Vipps\Payment\Gateway\Response;

use Magento\Payment\Gateway\{Data\PaymentDataObjectInterface, Response\HandlerInterface};
use Magento\Quote\{Api\CartRepositoryInterface, Model\Quote\Payment};
use Vipps\Payment\Gateway\Request\SubjectReader;

/**
 * Class InitiateHandler
 * @package Vipps\Payment\Gateway\Response
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class InitiateHandler implements HandlerInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * InitiateHandler constructor.
     *
     * @param CartRepositoryInterface $cartRepository
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        SubjectReader $subjectReader
    ) {
        $this->cartRepository = $cartRepository;
        $this->subjectReader = $subjectReader;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $handlingSubject
     * @param array $responseBody
     *
     * @throws \Exception
     */
    public function handle(array $handlingSubject, array $responseBody) //@codingStandardsIgnoreLine
    {
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();
        $quote = $payment->getQuote();
        $quote->setIsActive(false);
        $this->cartRepository->save($quote);
    }
}
