<?php

declare(strict_types=1);

namespace Vipps\Payment\Plugin\Config\Converter;

use Klarna\Base\Config\Converter\Dom;
use Magento\Framework\View\Asset\Repository;
use Vipps\Payment\Model\Config\ConfigVersionPool;
use Vipps\Payment\Model\Method\Vipps;

class DomPlugin
{
    private ConfigVersionPool $logoVersion;
    private Repository $repository;
    private Vipps $vipps;

    public function __construct(
        Vipps             $vipps,
        ConfigVersionPool $logoVersion,
        Repository        $repository
    ) {
        $this->logoVersion = $logoVersion;
        $this->repository = $repository;
        $this->vipps = $vipps;
    }

    public function afterConvert(Dom $subject, $result)
    {
        if (isset($result['external_payment_methods']['vipps'])) {
            $result['external_payment_methods']['vipps']['image_url'] = $this->repository->getUrlWithParams(
                $this->logoVersion->get(), ['_secure' => true]
            );

            $result['external_payment_methods']['vipps']['description'] = __("Checkout using %1", $this->vipps->getTitle());
        }

        return $result;
    }
}
