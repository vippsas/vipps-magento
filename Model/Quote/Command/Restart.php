<?php

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
     * @param QuoteInterface $vippsQuote
     */
    public function __construct(QuoteInterface $vippsQuote, QuoteRepository $quoteRepository)
    {
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
            [QuoteStatusInterface::STATUS_PLACE_FAILED, QuoteStatusInterface::STATUS_EXPIRED],
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
            ->setStatus(QuoteStatusInterface::STATUS_PROCESSING);

        $this->quoteRepository->save($this->vippsQuote);
    }
}
