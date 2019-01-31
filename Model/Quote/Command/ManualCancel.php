<?php

namespace Vipps\Payment\Model\Quote\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
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
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CancelFacade
     */
    private $cancelFacade;

    /**
     * Restart constructor.
     * @param QuoteInterface $vippsQuote
     * @param CartRepositoryInterface $cartRepository
     * @param CancelFacade $cancelFacade
     * @param Config $config
     */
    public function __construct(
        QuoteInterface $vippsQuote,
        CartRepositoryInterface $cartRepository,
        CancelFacade $cancelFacade,
        Config $config
    ) {
        $this->vippsQuote = $vippsQuote;
        $this->config = $config;
        $this->cartRepository = $cartRepository;
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
            [QuoteStatusInterface::STATUS_PLACE_FAILED, QuoteStatusInterface::STATUS_CANCEL_FAILED],
            true
        );
    }

    /**
     * @throws LocalizedException
     */
    public function execute()
    {
        try {
            $quote = $this->cartRepository->get($this->vippsQuote->getQuoteId());

            $this
                ->cancelFacade
                ->cancel($this->vippsQuote, $quote);
        } catch (\Throwable $exception) {
            throw new LocalizedException(__('Failed to cancel the order. Please contact support team.'));
        }
    }
}
