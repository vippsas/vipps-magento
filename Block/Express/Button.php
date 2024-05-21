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

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\Math\Random;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Catalog\Block\ShortcutInterface;

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
     * Button constructor.
     *
     * @param Template\Context $context
     * @param Random $mathRandom
     * @param ConfigInterface $config
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Random           $mathRandom,
        ConfigInterface  $config,
        Registry         $registry,
        array            $data = []
    ) {
        $this->config = $config;
        $this->assetRepo = $context->getAssetRepository();
        $this->mathRandom = $mathRandom;
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    private function getProduct(): ?ProductInterface
    {
        return $this->registry->registry('product');
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
            || !$this->config->getValue('express_checkout')) {
            return '';
        }
        if (!$this->getIsInCatalogProduct() &&
            !$this->config->getValue('checkout_cart_display')
        ) {
            return '';
        }

        if ($this->getIsInCatalogProduct()
            && !$this->config->getValue('virtual_products_display')
            && $this->getProduct()
            && $this->getProduct()->getTypeId() === \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL
        ) {
            return '';
        }

        return parent::_toHtml();
    }

    public function getVirtualProductDisplay(): bool
    {
        return (bool)$this->config->getValue('virtual_products_display');
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
}
