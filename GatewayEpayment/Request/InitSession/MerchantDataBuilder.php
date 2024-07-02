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
namespace Vipps\Payment\GatewayEpayment\Request\InitSession;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\UrlInterface;
use Vipps\Payment\GatewayEpayment\Request\SubjectReader;

class MerchantDataBuilder implements BuilderInterface
{
    private UrlInterface $urlBuilder;
    private SubjectReader $subjectReader;

    public function __construct(
        UrlInterface $urlBuilder,
        SubjectReader $subjectReader
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->subjectReader = $subjectReader;
    }

    /**
     * Get merchant related data for session request.
     *
     * @throws \Exception
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $callbackAuthorizationToken = $this->generateAuthToken();

        $reference = $paymentDO->getOrder()->getOrderIncrementId();

        return [
            'merchantInfo' => [
                'callbackUrl' => rtrim($this->urlBuilder->getUrl('checkout/vipps/callback'), '/'),
                'returnUrl' => $this->urlBuilder->getUrl('checkout/vipps/fallback', ['reference' => $reference]),
                'callbackAuthorizationToken' => $callbackAuthorizationToken,
                'termsAndConditionsUrl' => $this->urlBuilder->getUrl(),
            ]
        ];
    }

    /**
     * Method to generate access token.
     */
    private function generateAuthToken(): string
    {
        return uniqid('', true);
    }
}
