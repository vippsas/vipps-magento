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
declare(strict_types=1);

namespace Vipps\Payment\Model\Order\PartialVoid;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Config
 * @package Vipps\Payment\Model\Order\PartialVoid
 */
class Config
{
    /**
     * @const string
     */
    const XML_PATH_IS_ENABLED = 'payment/vipps/partial_void_enabled';

    /**
     * @const string
     */
    const XML_PATH_IS_SEND_MAIL_ENABLED = 'payment/vipps/partial_void_send_mail';

    /**
     * @const string
     */
    const XML_PATH_EMAIL_TEMPLATE = 'payment/vipps/partial_void_email_template';

    /**
     * @const string
     */
    const XML_PATH_EMAIL_SENDER = 'payment/vipps/partial_void_sender_email';

    /**
     * @const string
     */
    const XML_PATH_EMAIL_MESSAGE = 'payment/vipps/partial_void_mail_message';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param null $storeId
     *
     * @return bool
     */
    public function isOfflinePartialVoidEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_IS_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return bool
     */
    public function isSendMailEnabled($storeId = null): bool
    {
        return $this->scopeConfig
            ->isSetFlag(self::XML_PATH_IS_SEND_MAIL_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return string
     */
    public function getEmailTemplate($storeId = null): string
    {
        return $this->scopeConfig->getValue(self::XML_PATH_EMAIL_TEMPLATE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return string
     */
    public function emailSender($storeId = null): string
    {
        return $this->scopeConfig->getValue(self::XML_PATH_EMAIL_SENDER, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return string
     */
    public function getEmailMessage($storeId = null): string
    {
        return $this->scopeConfig->getValue(self::XML_PATH_EMAIL_MESSAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
