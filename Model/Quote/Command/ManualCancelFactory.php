<?php

namespace Vipps\Payment\Model\Quote\Command;

use Magento\Framework\ObjectManagerInterface;
use Vipps\Payment\Api\Data\QuoteInterface;

/**
 * Class RestartFactory
 */
class ManualCancelFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * RestartFactory constructor.
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param QuoteInterface $vippsQuote
     * @return ManualCancel
     */
    public function create(QuoteInterface $vippsQuote)
    {
        return $this->objectManager->create(ManualCancel::class, ['vippsQuote' => $vippsQuote]);
    }
}
