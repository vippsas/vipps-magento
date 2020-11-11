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
namespace Vipps\Payment\Model\Quote\Command;

use Magento\Framework\Exception\LocalizedException;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\Data\QuoteStatusInterface;
use Vipps\Payment\Model\Order\Cancellation\Config;
use Vipps\Payment\Model\Quote\CancelFacade;

/**
 * Restart Vipps Quote processing.
 */
class ManualCancel
{
    /**
     * @var QuoteInterface
     */
    private $vippsQuote;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var CancelFacade
     */
    private $cancelFacade;

    /**
     * Restart constructor.
     * @param QuoteInterface $vippsQuote
     * @param CancelFacade $cancelFacade
     * @param Config $config
     */
    public function __construct(
        QuoteInterface $vippsQuote,
        CancelFacade $cancelFacade,
        Config $config
    ) {
        $this->vippsQuote = $vippsQuote;
        $this->config = $config;
        $this->cancelFacade = $cancelFacade;
    }

    /**
     * Verify is Quote Processing allowed for restart.
     *
     * @return bool
     */
    public function isAllowed()
    {
        return in_array(
            $this->vippsQuote->getStatus(),
            [
                QuoteStatusInterface::STATUS_NEW,
                QuoteStatusInterface::STATUS_PENDING,
                QuoteStatusInterface::STATUS_RESERVE_FAILED,
                QuoteStatusInterface::STATUS_REVERT_FAILED
            ],
            true
        );
    }

    /**
     * @throws LocalizedException
     */
    public function execute()
    {
        try {
            $this->cancelFacade->cancel($this->vippsQuote);
        } catch (\Throwable $exception) {
            throw new LocalizedException(
                __('Failed to cancel the order. Please contact support team.')
            );
        }
    }
}
