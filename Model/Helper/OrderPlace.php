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
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\{CartRepositoryInterface, CartManagementInterface};
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

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
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * OrderPlace constructor.
     *
     * @param CartRepositoryInterface $quoteRepository
     * @param CartManagementInterface $cartManagement
     * @param SessionManagerInterface $customerSession
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        CartManagementInterface $cartManagement,
        SessionManagerInterface $customerSession
    ) {
        $this->cartManagement = $cartManagement;
        $this->customerSession = $customerSession;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param Quote $quote
     *
     * @return int
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function execute(Quote $quote)
    {
        // Here we need to active a quote
        // this active flag present only during this current request (does not stored in DB)
        // We should do this because when we called $this->quoteRepository->save()
        // the next request to repository e.g. $this->quoteRepository->getActive() - return quote
        // from DB with "isActive" = false;

        /** @var Quote $quote */
        $quote = $this->quoteRepository->get($quote->getId());
        $quote->setIsActive(true);

        // collect totals before place order
        $quote->collectTotals();

        return $this->cartManagement->placeOrder($quote->getId());
    }
}
