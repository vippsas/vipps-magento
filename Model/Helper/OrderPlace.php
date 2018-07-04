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
namespace Vipps\Payment\Model\Helper;

use Magento\Quote\Model\Quote;
use Magento\Checkout\{Helper\Data, Model\Type\Onepage};
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\{CartRepositoryInterface, CartManagementInterface};

/**
 * Class OrderPlace
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderPlace
{
    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var SessionManagerInterface
     */
    private $customerSession;

    /**
     * @var Data
     */
    private $checkoutHelper;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * OrderPlace constructor.
     *
     * @param CartRepositoryInterface $quoteRepository
     * @param CartManagementInterface $cartManagement
     * @param SessionManagerInterface $customerSession
     * @param Data $checkoutHelper
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        CartManagementInterface $cartManagement,
        SessionManagerInterface $customerSession,
        Data $checkoutHelper
    ) {
        $this->cartManagement = $cartManagement;
        $this->customerSession = $customerSession;
        $this->checkoutHelper = $checkoutHelper;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param Quote $quote
     *
     * @return int
     */
    public function execute(Quote $quote)
    {
        $this->updateCheckoutMethod($quote);
        return $this->cartManagement->placeOrder($quote->getId());
    }

    /**
     * Update quote checkout method.
     *
     * @param Quote $quote
     */
    private function updateCheckoutMethod(Quote $quote)
    {
        if (!$quote->getCheckoutMethod()) {
            if ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }
        $this->quoteRepository->save($quote);
        //We need to load Quote and activate it
        $quote = $this->quoteRepository->get($quote->getId());
        $quote->setIsActive(true);
    }
}
