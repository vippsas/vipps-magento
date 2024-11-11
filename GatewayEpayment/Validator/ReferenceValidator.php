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
namespace Vipps\Payment\GatewayEpayment\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Vipps\Payment\GatewayEpayment\Request\SubjectReader;

/**
 * Class OrderValidator
 * @package Vipps\Payment\Gateway\Validator
 */
class ReferenceValidator extends AbstractValidator
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;
    private OrderResourceInterface $orderResource;
    private OrderFactory $orderFactory;
    private QuoteFactory $quoteFactory;
    private Quote $quoteResource;

    /**
     * CaptureResponseValidator constructor.
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        SubjectReader $subjectReader,
        QuoteFactory $quoteFactory,
        Quote $quoteResource,
        OrderFactory $orderFactory
    ) {
        parent::__construct($resultFactory);
        $this->subjectReader = $subjectReader;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResource = $quoteResource;
    }

    /**
     * @inheritdoc
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $isValid = false;
        $reference = $this->subjectReader->readReference($validationSubject);
        $paymentDataObject = $this->subjectReader->readPayment($validationSubject);
        if ($reference && $paymentDataObject && $paymentDataObject->getPayment() && $paymentDataObject->getPayment()->getQuote()) {
            $isValid = $paymentDataObject->getPayment()->getQuote()->getReservedOrderId() === $reference;
        }

        $errorMessages = $isValid ? [] : [__('Gateway response error. Reference is incorrect')];

        return $this->createResult($isValid, $errorMessages);
    }
}
