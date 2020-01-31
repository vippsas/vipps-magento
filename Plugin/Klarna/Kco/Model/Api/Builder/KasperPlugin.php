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
namespace Vipps\Payment\Plugin\Klarna\Kco\Model\Api\Builder;

use Klarna\Kco\Model\Api\Builder\Kasper as Subject;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * Class KasperPlugin
 * @package Vipps\Payment\Plugin\Klarna\Kco\Model\Api\Builder
 */
class KasperPlugin
{
    /**
     * @var AssetRepository
     */
    private $assetRepository;

    /**
     * KasperPlugin constructor.
     *
     * @param AssetRepository $assetRepository
     */
    public function __construct(
        AssetRepository $assetRepository
    ) {
        $this->assetRepository = $assetRepository;
    }

    /**
     * @param Subject $subject
     * @param array $methods
     *
     * @return mixed
     */
    public function afterGetExternalMethods(Subject $subject, $methods)
    {
        if ($methods) {
            foreach ($methods as &$method) {
                if (strtolower($method['name']) == 'vipps') {
                    $method['image_url'] = $this->assetRepository->getUrl($method['image_url']);
                    break;
                }
            }
        }
        return $methods;
    }
}
