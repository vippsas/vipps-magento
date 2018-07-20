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
namespace Vipps\Payment\Observer;

use Magento\Catalog\Block\ShortcutButtons;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\Event\{ObserverInterface, Observer};
use Vipps\Payment\Block\Express\Button;

/**
 * Class AddVippsShortcutsObserver
 * @package Vipps\Payment\Observer
 */
class AddVippsShortcutsObserver implements ObserverInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * AddVippsShortcutsObserver constructor.
     *
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config
    ) {
        $this->config = $config;
    }

    /**
     * @param Observer $observer
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(Observer $observer)
    {
        if (!$this->config->getValue('express_checkout')) {
            return;
        }

        /** @var ShortcutButtons $shortcutButtons */
        $shortcutButtons = $observer->getEvent()->getContainer();

        /** @var Button $shortcut */
        $shortcut = $shortcutButtons->getLayout()->createBlock(
            Button::class,
            '',
            []
        );
        $shortcut->setIsInCatalogProduct(
            $observer->getEvent()->getIsCatalogProduct()
        );
        /** Adding vipps express checkout button to minicart extra_actions block */
        $shortcutButtons->addShortcut($shortcut);
    }
}
