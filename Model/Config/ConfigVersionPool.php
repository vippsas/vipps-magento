<?php

declare(strict_types=1);

namespace Vipps\Payment\Model\Config;

use Vipps\Payment\Gateway\Config\Config;
use Vipps\Payment\Model\Config\Source\Version;

class ConfigVersionPool
{
    private array $pool;
    private Config $config;

    public function __construct(
        array  $pool,
        Config $config
    ) {
        $this->pool = $pool;
        $this->config = $config;
    }

    public function get()
    {
        $versionCode = $this->config->getVersion();

        return $this->pool[$versionCode] ?? $this->pool[Version::CONFIG_VIPPS];
    }
}
