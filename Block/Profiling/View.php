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
namespace Vipps\Payment\Block\Profiling;

use Magento\Framework\View\Element\{Template, Template\Context};
use Vipps\Payment\Model\Profiling\Item;

/**
 * Class View
 * @package Vipps\Payment\Block\Profiling
 */
class View extends Template
{
    /**
     * @var Item
     */
    private $item;

    /**
     * View constructor.
     *
     * @param Context $context
     * @param Item $item
     * @param array $data
     */
    public function __construct(
        Context $context,
        Item $item,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->item = $item;
    }

    /**
     * Retrieve profiling item
     *
     * @return Item
     */
    public function getItem()
    {
        if (!$this->item->getId()) {
            $entityId = $this->getRequest()->getParam('entity_id', null);
            if ($entityId) {
                $this->item->load($entityId);
            }
        }
        return $this->item;
    }
}
