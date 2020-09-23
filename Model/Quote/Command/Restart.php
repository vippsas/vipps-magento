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

use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\Data\QuoteStatusInterface;
use Vipps\Payment\Model\Quote;
use Vipps\Payment\Model\QuoteRepository;

/**
 * Restart Vipps Quote processing.
 */
class Restart
{
    /**
     * @var Quote
     */
    private $vippsQuote;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * Restart constructor.
     *
     * @param QuoteInterface $vippsQuote
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(
        QuoteInterface $vippsQuote,
        QuoteRepository $quoteRepository
    ) {
        $this->vippsQuote = $vippsQuote;
        $this->quoteRepository = $quoteRepository;
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
                QuoteStatusInterface::STATUS_RESERVE_FAILED,
                QuoteStatusInterface::STATUS_EXPIRED
            ],
            true
        );
    }

    /**
     * Mark Vipps Quote as ready for restart.
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function execute()
    {
        $this
            ->vippsQuote
            ->clearAttempts()
            ->setStatus(QuoteStatusInterface::STATUS_PENDING);

        $this->quoteRepository->save($this->vippsQuote);
    }
}
