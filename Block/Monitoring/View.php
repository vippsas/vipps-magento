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

namespace Vipps\Payment\Block\Monitoring;

use Magento\Framework\View\Element\Template;
use Magento\Quote\Api\CartRepositoryInterface;
use Vipps\Payment\Model\QuoteRepository as vippsQuoteRepository;

/**
 * View Quote Monitoring entity.
 */
class View extends Template
{
    /**
     * @var vippsQuoteRepository
     */
    private $vippsQuoteRepository;
    /**
     * @var
     */
    private $vippsQuote;
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    private $quote;
    /**
     * @var string|null
     */
    private $quoteLoadingError = null;

    /**
     * View constructor.
     * @param vippsQuoteRepository $monitoringQuoteRepository
     * @param CartRepositoryInterface $quoteRepository
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        vippsQuoteRepository $monitoringQuoteRepository,
        CartRepositoryInterface $quoteRepository,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->vippsQuoteRepository = $monitoringQuoteRepository;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @return \Magento\Quote\Api\Data\CartInterface
     */
    public function getQuote()
    {
        if (!$this->quote) {
            try {
                $this->quote = $this->quoteRepository->get($this->getVippsQuote()->getQuoteId());
            } catch (\Exception $e) {
                $this->quoteLoadingError = $e->getMessage();
            }
        }

        return $this->quote;
    }

    /**
     * @return \Vipps\Payment\Api\Data\QuoteInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getVippsQuote()
    {
        if (!$this->vippsQuote) {
            try {
                $this->vippsQuote = $this->vippsQuoteRepository->load($this->getRequest()->getParam('entity_id'));
            } catch (\Exception $e) {
                // Display this error in template.
            }
        }

        return $this->vippsQuote;
    }

    /**
     * Quote loading error.
     *
     * @return string|null
     */
    public function getQuoteLoadingError()
    {
        return $this->quoteLoadingError;
    }
}
