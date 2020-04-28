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

namespace Vipps\Payment\Controller\Adminhtml\Monitoring;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Vipps\Payment\Model\Quote\Command\RestartFactory;
use Vipps\Payment\Model\QuoteRepository;

/**
 * Class Restart
 */
class Restart extends Action
{
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var RestartFactory
     */
    private $restartFactory;

    /**
     * Restart constructor.
     *
     * @param Context $context
     * @param QuoteRepository $quoteRepository
     * @param RestartFactory $restartFactory
     */
    public function __construct(
        Context $context,
        QuoteRepository $quoteRepository,
        RestartFactory $restartFactory
    ) {
        parent::__construct($context);
        $this->quoteRepository = $quoteRepository;
        $this->restartFactory = $restartFactory;
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        try {
            $this
                ->getRestart()
                ->execute();
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this
            ->resultRedirectFactory
            ->create()
            ->setUrl($this->_redirect->getRefererUrl());
    }

    /**
     * @return \Vipps\Payment\Model\Quote\Command\Restart
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getRestart()
    {
        $vippsQuote = $this
            ->quoteRepository
            ->load($this->getRequest()->getParam('entity_id'));

        return $this->restartFactory->create($vippsQuote);
    }
}
