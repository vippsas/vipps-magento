<?php
/**
 * Copyright 2018 Vipps
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 *  documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 *  the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 *  and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED
 *  TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL
 *  THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 *  CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 *  IN THE SOFTWARE.
 *
 */

namespace Vipps\Payment\Model\Monitoring;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Vipps\Payment\Api\Monitoring\{Data\QuoteInterface, QuoteManagementInterface};

/**
 * Class QuoteRepository
 */
class QuoteManagement implements QuoteManagementInterface
{
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var \Vipps\Payment\Model\Monitoring\QuoteRepository
     */
    private $quoteRepository;

    /**
     * QuoteManagement constructor.
     * @param QuoteFactory $quoteFactory
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(
        QuoteFactory $quoteFactory,
        QuoteRepository $quoteRepository
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param CartInterface $cart
     * @return QuoteInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function create(CartInterface $cart)
    {
        /** @var Quote $monitoringQuote */
        $monitoringQuote = $this->quoteFactory->create();

        $monitoringQuote
            ->setQuoteId($cart->getId())
            ->setReservedOrderId($cart->getReservedOrderId());

        return $this->quoteRepository->save($monitoringQuote);
    }

    /**
     * Loads Vipps monitoring as extension attribute.
     *
     * @param CartInterface $quote
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function loadExtensionAttribute(CartInterface $quote)
    {
        if ($extensionAttributes = $quote->getExtensionAttributes()) {
            if (!$extensionAttributes->getVippsMonitoring()) {

                $monitoringQuote = $this->getByQuote($quote);

                $extensionAttributes->setVippsMonitoring($monitoringQuote);
            }
        }
    }

    /**
     * @param CartInterface $cart
     * @return Quote
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function getByQuote(CartInterface $cart)
    {
        /** @var Quote $monitoringQuote */
        try {
            $monitoringQuote = $this->quoteRepository->loadByQuote($cart->getId());
        } catch (NoSuchEntityException $exception) {
            // Setup default values for backward compatibility with current quotes.
            $monitoringQuote = $this->quoteFactory->create()
                ->setQuoteId($cart->getId())
                ->setReservedOrderId($cart->getReservedOrderId());

            // Backward compatibility for old quotes paid with vipps.
            $this->quoteRepository->save($monitoringQuote);
        }

        return $monitoringQuote;
    }
}
