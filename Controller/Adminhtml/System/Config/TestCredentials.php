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
namespace Vipps\Payment\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filter\StripTags;
use Magento\Store\Model\StoreManagerInterface;
use Vipps\Payment\Model\TokenProviderInterface;

class TestCredentials extends Action
{
    /**
     * Authorization level of a basic admin session.
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Vipps_Payment::config';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var StripTags
     */
    private $tagFilter;

    /**
     * @var TokenProviderInterface
     */
    private $tokenProvider;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * TestCredentials constructor.
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param StripTags $tagFilter
     * @param TokenProviderInterface $tokenProvider
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        StripTags $tagFilter,
        TokenProviderInterface $tokenProvider,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->tagFilter = $tagFilter;
        $this->tokenProvider = $tokenProvider;
        $this->storeManager = $storeManager;
    }

    /**
     * Check for connection to server
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = [
            'success' => false,
            'errorMessage' => '',
        ];

        try {
            $options = $this->getRequest()->getParams();

            $storeId = $options['store_switcher'] ?? 0;
            $storeGroupId = $options['store_group_switcher'] ?? 0;
            $websiteId = $options['website_switcher'] ?? 0;

            if ($storeId) {
                $this->storeManager->setCurrentStore($storeId);
            } elseif ($storeGroupId) {
                $storeId = $this->storeManager->getGroup($storeGroupId)->getDefaultStoreId();
                $this->storeManager->setCurrentStore($storeId);
            } elseif ($websiteId) {
                $storeGroupId = $this->storeManager->getWebsite($websiteId)->getDefaultGroupId();
                $storeId = $this->storeManager->getGroup($storeGroupId)->getDefaultStoreId();
                $this->storeManager->setCurrentStore($storeId);
            }

            $this->tokenProvider->regenerate();
            $result['success'] = true;
        } catch (\Throwable $t) {
            $message = $t->getMessage();
            if ($t->getPrevious()) {
                $message .= PHP_EOL . $t->getPrevious()->getMessage();
            }

            $result['errorMessage'] = $this->tagFilter->filter($message);
        }

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($result);
    }
}
