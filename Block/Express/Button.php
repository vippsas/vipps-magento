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

namespace Vipps\Payment\Block\Express;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\Math\Random;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Catalog\Block\ShortcutInterface;
use Vipps\Payment\Model\Config\Source\Version;
use Magento\Framework\Locale\Resolver;

/**
 * Class Button
 * @package Vipps\Payment\Block\Express
 */
class Button extends Template implements ShortcutInterface
{
    /**
     * Shortcut alias
     *
     * @var string
     */
    private $alias = 'vipps.express.minicart.button';

    /**
     * @var Repository
     */
    private $assetRepo;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var Random
     */
    private $mathRandom;

    /**
     * Path to template file in theme.
     *
     * @var string
     */
    protected $_template = "Vipps_Payment::button.phtml"; //@codingStandardsIgnoreLine

    /**
     * @var Resolver
     */
    protected $localeResolver;

    /**
     * Button constructor.
     *
     * @param Context $context
     * @param Random $mathRandom
     * @param ConfigInterface $config
     * @param Resolver $localeResolver
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Random           $mathRandom,
        ConfigInterface  $config,
        Resolver $localeResolver,
        array            $data = []
    ) {
        $this->config = $config;
        $this->assetRepo = $context->getAssetRepository();
        $this->mathRandom = $mathRandom;
        $this->localeResolver = $localeResolver;
        parent::__construct($context, $data);
    }

    /**
     * Disable block output if button visibility is turned off.
     *
     * {@inheritdoc}
     *
     * @return string
     */
    protected function _toHtml() //@codingStandardsIgnoreLine
    {
        if (!$this->config->getValue('active')
            || !$this->config->getValue('express_checkout')
            || $this->config->getValue('version') !== Version::CONFIG_VIPPS
        ) {
            return '';
        }
        if (!$this->getIsInCatalogProduct() &&
            !$this->config->getValue('checkout_cart_display')
        ) {
            return '';
        }

        return parent::_toHtml();
    }

    public function getLabel()
    {
        return $this->config->getValue('version') === Version::CONFIG_VIPPS
            ? __('Vipps')
            : __('MobilePay');
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractBlock
     */
    protected function _beforeToHtml() //@codingStandardsIgnoreLine
    {
        $this->setShortcutHtmlId(
            $this->mathRandom->getUniqueHash('ec_shortcut_')
        );
        return parent::_beforeToHtml();
    }

    /**
     * Returns full image Url.
     *
     * @param $imagePath
     *
     * @return string
     */
    public function getImageUrl($imagePath)
    {
        return $this->_assetRepo->getUrl($imagePath);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getAlias()
    {
        if ($this->getIsInCatalogProduct()) {
            $this->alias = 'vipps.express.catalog_product.button';
        }
        return $this->alias;
    }

    /**
     * @return mixed
     */
    public function getLocale()
    {
        return $this->localeResolver->getLocale();
    }

    /**
     * Return button language based on store locale
     *
     * @return string
     */
    public function getLanguage()
    {
        $language = strtolower(substr($this->getLocale(), 3, 2)); // region part of the locale: us, no, se, dk, fi

        // Vipps button
        if (in_array($language, ['no', 'se', 'en']) && $this->config->getValue('version') === Version::CONFIG_VIPPS) {
            return $language;
        }

        // MobilePay button
        if (in_array($language, ['dk', 'fi', 'en']) && $this->config->getValue('version') === Version::CONFIG_MOBILE_EPAYMENT) {
            return $language;
        }

        return 'en'; // Default to English if unsupported language
    }

    /**
     * Returns the inline SVG markup for the button matching the current brand and language.
     *
     * Resolves the file via the asset repository so it works regardless of install location
     * (app/code or vendor/ via Composer).
     *
     * @return string
     */
    public function getButtonImage()
    {
        $brand = $this->config->getValue('version') === Version::CONFIG_VIPPS ? 'Vipps' : 'MobilePay';
        $file = $this->assetRepo
            ->createAsset(sprintf(
                'Vipps_Payment::images/buttons/%s/%s-%s.svg',
                $brand,
                strtolower($brand),
                $this->getLanguage()
            ))
            ->getSourceFile();

        return file_exists($file) ? file_get_contents($file) : '';
    }
}
