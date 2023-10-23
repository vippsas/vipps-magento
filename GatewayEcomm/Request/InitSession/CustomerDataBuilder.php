<?php
/**
 * Copyright 2022 Vipps
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
namespace Vipps\Payment\GatewayEcomm\Request\InitSession;

use Magento\Customer\Model\Customer;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Customer\Model\Session;

/**
 * Class CustomerDataBuilder
 * @package Vipps\Payment\GatewayEcomm\Request\InitSession
 */
class CustomerDataBuilder implements BuilderInterface
{
    /**
     * @var SessionManagerInterface|Session
     */
    private SessionManagerInterface $customerSession;

    public function __construct(
        SessionManagerInterface $customerSession
    ) {
        $this->customerSession = $customerSession;
    }

    /**
     * Get related data for transaction section.
     *
     * @param array $buildSubject
     *
     * @return array
     * @throws \Exception
     */
    public function build(array $buildSubject)
    {
        $data = [];

        /** @var Customer $customer */
        $customer = $this->customerSession->getCustomer();
        if ($customer && $customer->getDefaultBillingAddress()) {
            $data['prefillCustomer'] = [
                'firstName' => $customer->getFirstname(),
                'lastName' => $customer->getLastname(),
                'email' => $customer->getEmail(),
                'phoneNumber' => $customer->getDefaultBillingAddress()->getTelephone(),
                'streetAddress' => $customer->getDefaultBillingAddress()->getStreetFull(),
                'city' => $customer->getDefaultBillingAddress()->getCity(),
                'postalCode' => $customer->getDefaultBillingAddress()->getPostcode(),
                'country' => $customer->getDefaultBillingAddress()->getCountry()
            ];
        }

        return $data;
    }
}
