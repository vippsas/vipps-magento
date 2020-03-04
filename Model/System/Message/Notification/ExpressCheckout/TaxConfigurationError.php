<?php
/**
 * Copyright 2018 Vipps
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
namespace Vipps\Payment\Model\System\Message\Notification\ExpressCheckout;

use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

/**
 * Class TaxConfigurationError
 */
class TaxConfigurationError implements \Magento\Tax\Model\System\Message\NotificationInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var TaxConfig
     */
    private $taxConfig;

    /**
     * Websites with invalid discount settings
     *
     * @var array
     */
    private $storesWithInvalidSettings;

    /**
     * @var ConfigInterface
     */
    private $paymentConfig;

    /**
     * @var Data
     */
    private $taxHelper;

    /**
     * TaxConfigurationError constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param TaxConfig $taxConfig
     * @param ConfigInterface $paymentConfig
     * @param Data $taxHelper
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        TaxConfig $taxConfig,
        ConfigInterface $paymentConfig,
        Data $taxHelper
    ) {
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->taxConfig = $taxConfig;
        $this->paymentConfig = $paymentConfig;
        $this->taxHelper = $taxHelper;
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function getIdentity()
    {
        return 'VIPPS_NOTIFICATION_TAX_CONFIGURATION_ERROR';
    }

    /**
     * {@inheritdoc}
     */
    public function isDisplayed()
    {
        if ($this->paymentConfig->getValue('express_checkout') && !empty($this->getStoresWithWrongSettings())) {
            return true;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getText()
    {
        $messageDetails = '';

        if ($this->isDisplayed()) {
            $messageDetails .= '<strong>';
            $messageDetails .= __('Tax calculation should be based on “Shipping Origin”,'
                . ' otherwise Vipps Express Checkout may not work properly. ');
            $messageDetails .= '</strong><p>';
            $messageDetails .= __('Store(s) affected: ');
            $messageDetails .= implode(', ', $this->getStoresWithWrongSettings());
            $messageDetails .= '</p><p>';
            $messageDetails .= __(
                'Click here to go to <a href="%1">Tax Configuration</a> and change your settings.',
                $this->urlBuilder->getUrl('adminhtml/system_config/edit/section/tax')
            );
            $messageDetails .= '</p>';
        }

        return $messageDetails;
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function getSeverity()
    {
        return self::SEVERITY_CRITICAL;
    }

    /**
     * @param null $store
     *
     * @return bool
     */
    private function checkSettings($store = null)
    {
        return 'origin' == $this->taxHelper->getTaxBasedOn($store);
    }

    /**
     * Return list of store names where tax discount settings are compatible.
     * Return true if settings are wrong for default store.
     *
     * @return array
     */
    private function getStoresWithWrongSettings()
    {
        if (null !== $this->storesWithInvalidSettings) {
            return $this->storesWithInvalidSettings;
        }
        $this->storesWithInvalidSettings = [];
        $storeCollection = $this->storeManager->getStores(true);
        foreach ($storeCollection as $store) {
            if (!$this->checkSettings($store)) {
                $website = $store->getWebsite();
                $this->storesWithInvalidSettings[] = $website->getName() . ' (' . $store->getName() . ')';
            }
        }
        return $this->storesWithInvalidSettings;
    }
}
