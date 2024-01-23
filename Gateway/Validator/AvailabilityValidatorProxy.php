<?php declare(strict_types=1);

namespace Vipps\Payment\Gateway\Validator;

use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Vipps\Payment\Model\Config\ConfigVersionPool;

class AvailabilityValidatorProxy implements ValidatorInterface
{
    private ConfigVersionPool $configVersionPool;

    public function __construct(ConfigVersionPool $configVersionPool)
    {
        $this->configVersionPool = $configVersionPool;
    }

    private function get(): ValidatorInterface
    {
        return $this->configVersionPool->get();
    }
    
    public function validate(array $validationSubject)
    {
        return $this->get()->validate($validationSubject);
    }
}
