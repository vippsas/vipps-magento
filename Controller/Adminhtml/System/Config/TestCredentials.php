<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
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
