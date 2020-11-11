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

namespace Vipps\Payment\Block\Monitoring;

use Magento\Framework\Pricing\Helper\Data;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Quote\Api\CartRepositoryInterface;
use Vipps\Payment\Model\Quote\AttemptRepository;
use Vipps\Payment\Model\QuoteRepository as VippsQuoteRepository;
use Vipps\Payment\Ui\Component\Column\Status;

/**
 * View Quote Monitoring entity.
 */
class View extends Template
{
    /**
     * @var VippsQuoteRepository
     */
    private $vippsQuoteRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var string
     */
    private $quoteLoadingError = '';

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Data
     */
    private $priceHelper;

    /**
     * @var AttemptRepository
     */
    private $attemptRepository;
    /**
     * @var Status
     */
    private $status;

    /**
     * View constructor.
     * @param VippsQuoteRepository $vippsQuoteRepository
     * @param CartRepositoryInterface $quoteRepository
     * @param AttemptRepository $attemptRepository
     * @param Registry $registry
     * @param Data $priceHelper
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        VippsQuoteRepository $vippsQuoteRepository,
        CartRepositoryInterface $quoteRepository,
        AttemptRepository $attemptRepository,
        Status $status,
        Registry $registry,
        Data $priceHelper,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->vippsQuoteRepository = $vippsQuoteRepository;
        $this->quoteRepository = $quoteRepository;
        $this->registry = $registry;
        $this->priceHelper = $priceHelper;
        $this->attemptRepository = $attemptRepository;
        $this->status = $status;
    }

    /**
     * @return Data
     */
    public function getPriceHelper()
    {
        return $this->priceHelper;
    }

    /**
     * @return \Magento\Quote\Api\Data\CartInterface
     */
    public function getQuote()
    {
        try {
            return $this->quoteRepository->get($this->getVippsQuote()->getQuoteId());
        } catch (\Exception $e) {
            $this->quoteLoadingError = $e->getMessage();
        }
    }

    /**
     * @return \Vipps\Payment\Api\Data\QuoteInterface
     */
    public function getVippsQuote()
    {
        return $this->registry->registry('vipps_quote');
    }

    /**
     * Quote loading error.
     *
     * @return string
     */
    public function getQuoteLoadingError()
    {
        return $this->quoteLoadingError;
    }

    /**
     * @return \Vipps\Payment\Model\ResourceModel\Quote\Attempt\Collection
     */
    public function getAttempts()
    {
        return $this
            ->attemptRepository
            ->getByVippsQuote($this->getVippsQuote())
            ->load();
    }

    /**
     * @param string $code
     * @return string
     */
    public function getStatusLabel($code)
    {
        return $this->status->getLabel($code);
    }

    /**
     * Retrieve formatting date
     *
     * @param null|string|\DateTimeInterface $date
     * @param int $format
     * @param bool $showTime
     * @param null|string $timezone
     * @return string
     */
    public function formatDate(  //@codingStandardsIgnoreLine
        $date = null,
        $format = \IntlDateFormatter::MEDIUM,
        $showTime = true,
        $timezone = null
    ) {
        return parent::formatDate($date, $format, $showTime, $timezone);
    }
}
