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
namespace Vipps\Payment\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class InitiateValidator
 * @package Vipps\Payment\Gateway\Validator
 */
class AvailabilityValidator extends AbstractValidator
{
    const NORWEGIAN_CURRENCY = 'NOK';

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * CurrencyValidator constructor.
     *
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($resultFactory);
        $this->storeManager = $storeManager;
    }

    /**
     * @param array $validationSubject
     *
     * @return ResultInterface
     * @throws NoSuchEntityException
     */
    public function validate(array $validationSubject)
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();

        $isValid = self::NORWEGIAN_CURRENCY == $store->getBaseCurrencyCode();
        $errorMessages = $isValid ? [] : [__('Not allowed currency. Please, contact store administrator.')];

        return $this->createResult($isValid, $errorMessages);
    }
}
