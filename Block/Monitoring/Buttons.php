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

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Vipps\Payment\Model\Quote\Command\ManualCancelFactory;
use Vipps\Payment\Model\Quote\Command\RestartFactory;

/**
 * View Quote Monitoring entity.
 */
class Buttons extends Template
{
    /**
     * @var RestartFactory
     */
    private $restartFactory;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var ManualCancelFactory
     */
    private $cancelFactory;

    /**
     * Buttons constructor.
     * @param RestartFactory $restartFactory
     * @param Registry $registry
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        RestartFactory $restartFactory,
        Registry $registry,
        ManualCancelFactory $cancelFactory,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->restartFactory = $restartFactory;
        $this->registry = $registry;
        $this->cancelFactory = $cancelFactory;
    }

    /**
     * @return bool
     */
    public function isRestartVisible()
    {
        $restart = $this->restartFactory->create($this->getVippsQuote());

        return $restart->isAllowed();
    }

    /**
     * @return \Vipps\Payment\Api\Data\QuoteInterface
     */
    public function getVippsQuote()
    {
        return $this->registry->registry('vipps_quote');
    }

    /**
     * @return bool
     */
    public function isCancelVisible()
    {
        $cancel = $this->cancelFactory->create($this->getVippsQuote());

        return $cancel->isAllowed();
    }
}
