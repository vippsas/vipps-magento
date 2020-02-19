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

namespace Vipps\Payment\Model\Quote;

use Vipps\Payment\Model\{Quote as VippsQuote, Quote\AttemptFactory, QuoteRepository as QuoteMonitorRepository};

/**
 * Attempt Management.
 */
class AttemptManagement
{
    /**
     * @var AttemptFactory
     */
    private $attemptFactory;

    /**
     * @var QuoteMonitorRepository
     */
    private $quoteMonitorRepository;

    /**
     * @var AttemptRepository
     */
    private $attemptRepository;

    /**
     * AttemptManagement constructor.
     *
     * @param AttemptFactory $attemptFactory
     * @param QuoteMonitorRepository $quoteRepository
     * @param AttemptRepository $attemptRepository
     */
    public function __construct(
        AttemptFactory $attemptFactory,
        QuoteMonitorRepository $quoteRepository,
        AttemptRepository $attemptRepository
    ) {
        $this->attemptFactory = $attemptFactory;
        $this->quoteMonitorRepository = $quoteRepository;
        $this->attemptRepository = $attemptRepository;
    }

    /**
     * Create new saved attempt. Increment attempt count. Fill it with message later.
     *
     * @param VippsQuote $quote
     *
     * @return Attempt
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function createAttempt(VippsQuote $quote)
    {
        $attempt = $this->attemptFactory
            ->create(['data' => ['parent_id' => $quote->getId()]])
            ->setDataChanges(true);
        return $attempt;
    }

    /**
     * @param Attempt $attempt
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(Attempt $attempt)
    {
        $this->attemptRepository->save($attempt);
    }
}
