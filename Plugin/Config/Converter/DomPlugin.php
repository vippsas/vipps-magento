<?php

declare(strict_types=1);

namespace Vipps\Payment\Plugin\Config\Converter;

use Klarna\Base\Config\Converter\Dom;
use Magento\Framework\View\Asset\Repository;
use Vipps\Payment\Model\Config\ConfigVersionPool;

class DomPlugin
{
    private ConfigVersionPool $logoVersion;
    private Repository $repository;

    public function __construct(
        ConfigVersionPool $logoVersion,
        Repository        $repository
    ) {
        $this->logoVersion = $logoVersion;
        $this->repository = $repository;
    }

    public function afterConvert(Dom $subject, $result)
    {
        if (isset($result['external_payment_methods']['vipps'])) {
            $result['external_payment_methods']['vipps']['image_url'] = $this->repository->getUrlWithParams(
                $this->logoVersion->get(), ['_secure' => true]
            );
        }

        return $result;
    }
}
