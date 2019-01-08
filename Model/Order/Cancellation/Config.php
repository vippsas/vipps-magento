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

namespace Vipps\Payment\Model\Order\Cancellation;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Vipps\Payment\Model\Adminhtml\Source\Cancellation\Type;

/**
 * Class Config
 */
class Config
{
    /**
     * @const string
     */
    const XML_PATH_TYPE = 'vipps/order_cancellation/type';

    /**
     * @const string
     */
    const XML_PATH_ATTEMPT_COUNT = 'vipps/order_cancellation/attempts_count';

    /**
     * @const string
     */
    const XML_PATH_INACTIVITY_TIME = 'vipps/order_cancellation/customer_inactivity_time';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isTypeAutomatic($storeId = null)
    {
        return $this->getType($storeId) === Type::AUTOMATIC;
    }

    /**
     * Cancellation type code.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getType($storeId = null)
    {
        return $this->getStoreConfig(self::XML_PATH_TYPE, $storeId);
    }

    /**
     * Common method to return store config value.
     *
     * @param $path
     * @param int|null $storeId
     * @return mixed
     */
    private function getStoreConfig($path, $storeId = null)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Number of failed attempts.
     *
     * @return int
     */
    public function getAttemptsMaxCount()
    {
        return $this->getStoreConfig(self::XML_PATH_ATTEMPT_COUNT);
    }

    /**
     * Return inactivity time in minutes.
     *
     * @return int
     */
    public function getInactivityTime()
    {
        return $this->getStoreConfig(self::XML_PATH_INACTIVITY_TIME);
    }
}
