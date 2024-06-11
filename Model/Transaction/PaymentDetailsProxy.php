<?php declare(strict_types=1);

namespace Vipps\Payment\Model\Transaction;

use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Model\Config\ConfigVersionPool;

class PaymentDetailsProxy
{
    private ConfigVersionPool $configVersionPool;

    public function __construct(ConfigVersionPool $configVersionPool)
    {
        $this->configVersionPool = $configVersionPool;
    }

    private function getPaymentDetailsCommand(): \Vipps\Payment\Api\Transaction\PaymentDetailsInterface
    {
        return $this->configVersionPool->get();
    }

    public function get($incrementId)
    {
        return $this->getPaymentDetailsCommand()->get($incrementId);
    }
}
