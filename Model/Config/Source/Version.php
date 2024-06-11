<?php

declare(strict_types=1);

namespace Vipps\Payment\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Version implements ArrayInterface
{
    public const CONFIG_VIPPS = 'vipps_payment';
    public const CONFIG_MOBILE_EPAYMENT = 'mobile_epayment';

    private const LABEL_VIPPS = 'Vipps';
    private const LABEL_MOBILE_PAY = 'MobilePay';

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::CONFIG_VIPPS, 'label' => __(self::LABEL_VIPPS)],
            ['value' => self::CONFIG_MOBILE_EPAYMENT, 'label' => __(self::LABEL_MOBILE_PAY)]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [self::CONFIG_VIPPS => __(self::LABEL_VIPPS), self::CONFIG_MOBILE_EPAYMENT => __(self::LABEL_MOBILE_PAY)];
    }
}
