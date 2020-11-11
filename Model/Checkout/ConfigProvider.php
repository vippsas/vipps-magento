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
namespace Vipps\Payment\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\UrlInterface;

/**
 * Class ConfigProvider
 * @package Vipps\Payment\Model\Checkout
 */
class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var AssetRepository
     */
    private $assertRepository;

    /**
     * ConfigProvider constructor.
     *
     * @param UrlInterface $urlBuilder
     * @param AssetRepository $assertRepository
     */
    public function __construct(
        UrlInterface $urlBuilder,
        AssetRepository $assertRepository
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->assertRepository = $assertRepository;
    }

    public function getConfig()
    {
        return [
            'payment' => [
                'vipps' => [
                    'initiateUrl' => $this->urlBuilder->getUrl('vipps/payment/initRegular', ['_secure' => true]),
                    'logoSrc' => $this->assertRepository->getUrl('Vipps_Payment::images/vipps_logo_rgb.png'),
                    'continueImgSrc' =>
                        $this->assertRepository->getUrl('Vipps_Payment::images/vipps_knapp_fortsett.png'),
                ]
            ]
        ];
    }
}
