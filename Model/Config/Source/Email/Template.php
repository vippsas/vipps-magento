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
declare(strict_types=1);

namespace Vipps\Payment\Model\Config\Source\Email;

use Magento\Config\Model\Config\Source\Email\Template as EmailTemplate;
use Magento\Config\Model\Config\Structure\SearchInterface;
use Magento\Email\Model\ResourceModel\Template\CollectionFactory;
use Magento\Email\Model\Template\Config;
use Magento\Framework\Registry;

/**
 * Class Template
 * @package Vipps\Payment\Model\Config\Source\Email
 */
class Template extends EmailTemplate
{
    /**
     * @var SearchInterface
     */
    private $config;

    /**
     * Template constructor.
     *
     * @param Registry $coreRegistry
     * @param CollectionFactory $templatesFactory
     * @param Config $emailConfig
     * @param SearchInterface $config
     * @param array $data
     */
    public function __construct(
        Registry $coreRegistry,
        CollectionFactory $templatesFactory,
        Config $emailConfig,
        SearchInterface $config,
        array $data = []
    ) {
        parent::__construct($coreRegistry, $templatesFactory, $emailConfig, $data);
        $this->config = $config;
    }

    /**
     * Replace config path for correct rendering default template option
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $element = $this->config->getElement($this->getPath());
        $configData = $element->getData();
        $this->setPath($configData['config_path']);
        return parent::toOptionArray();
    }
}
