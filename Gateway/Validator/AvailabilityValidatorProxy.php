<?php declare(strict_types=1);
/**
 * Copyright 2023 Vipps
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
