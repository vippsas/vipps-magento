<?php declare(strict_types=1);

namespace Vipps\Payment\Model;

use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Model\Config\ConfigVersionPool;

class CommandPoolProxy implements CommandPoolInterface
{
    private ConfigVersionPool $configVersionPool;

    public function __construct(ConfigVersionPool $configVersionPool)
    {
        $this->configVersionPool = $configVersionPool;
    }

    private function getPool(): CommandPoolInterface
    {
        return $this->configVersionPool->get();
    }

    public function get($commandCode): CommandInterface
    {
        return $this->getPool()->get($commandCode);
    }
}
