<?php
/**
 * Copyright 2018 Vipps
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
namespace Vipps\Payment\Gateway\Request\Initiate;

use Magento\Payment\Gateway\{
    Data\PaymentDataObjectInterface, Request\BuilderInterface
};
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\{Quote, Quote\Payment};
use Magento\Framework\UrlInterface;
use Vipps\Payment\Gateway\Request\SubjectReader;

/**
 * Class MerchantInfo
 * @package Vipps\Payment\Gateway\Request\InitiateData
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class MerchantDataBuilder implements BuilderInterface
{
    /**
     * Merchant auth token identifier
     *
     * @var string
     */
    const MERCHANT_AUTH_TOKEN = 'merchant_auth_token';

    /**
     * Auth token for accessing to fallback controller
     *
     * @var string
     */
    const FALLBACK_AUTH_TOKEN = 'fallback_auth_token';

    /**
     * @var string
     */
    const EXPRESS_CHECKOUT = "eComm Express Payment";

    /**
     * @var string
     */
    const REGULAR_PAYMENT = "eComm Regular Payment";

    /**
     * This parameter will identify difference between ecomm payment and ecomm express payment.
     *
     * @var string
     */
    const PAYMENT_TYPE = 'paymentType';

    /**
     * Merchant info block name
     *
     * @var string
     */
    private static $merchantInfo = 'merchantInfo';

    /**
     * This is to receive the callback after the payment request. Domain name and context path should be provided by
     * merchant as the value for this parameter. The rest of the URL will be appended by Vipps according to Vipps
     * guidelines. Value must be less than or equal to 255 characters.
     *
     * @var string
     */
    private static $callbackPrefix = 'callbackPrefix';

    /**
     * Vipps will use the fall back URL to redirect Merchant Page once Payment is completed in Vipps System.
     * Value must be less than or equal to 255 characters.
     *
     * @var string
     */
    private static $fallBack = 'fallBack';

    /**
     * This parameter indicates whether payment request is triggered from Mobile App or Web browser. Based on this
     * value, response will be redirect url for Vipps landing page or deeplink Url to connect vipps App.
     * Boolean. OPTIONAL.
     *
     * @var string
     */
    private static $isApp = 'isApp';

    /**
     * This is to receive callbacks from Vipps by authentication mechanism for making callbacks secure.
     *
     * @var string
     */
    private static $authToken = 'authToken';

    /**
     * In case of express checkout payment, merchant should pass this prefix to let
     * Vipps fetch shipping cost and method related details
     *
     * @var string
     */
    private static $shippingDetailsPrefix = 'shippingDetailsPrefix';

    /**
     * Allows Vipps to send consent removal request to merchant.
     * After this merchant is obliged to remove the user details from merchant system permanently,
     * as per the GDPR guidelines.
     *
     * @var string
     */
    private static $consentRemovalPrefix = 'consentRemovalPrefix';

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * MerchantDataBuilder constructor.
     *
     * @param UrlInterface $urlBuilder
     * @param SubjectReader $subjectReader
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        UrlInterface $urlBuilder,
        SubjectReader $subjectReader,
        CartRepositoryInterface $cartRepository
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->subjectReader = $subjectReader;
        $this->cartRepository = $cartRepository;
    }

    /**
     * Get merchant related data for Initiate payment request.
     *
     * @param array $buildSubject
     *
     * @return array
     * @throws \Exception
     */
    public function build(array $buildSubject)
    {
        $callBackAuthToken = $this->generateAuthToken();
        $fallBackAuthToken = $this->generateAuthToken();

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();
        $payment->setAdditionalInformation(self::MERCHANT_AUTH_TOKEN, $callBackAuthToken);
        $payment->setAdditionalInformation(self::FALLBACK_AUTH_TOKEN, $fallBackAuthToken);
        $quote = $this->cartRepository->get($payment->getQuote()->getId());
        /** @var $quote Quote */
        $quote->reserveOrderId();
        $merchantInfo = [
            self::$merchantInfo => [
                self::$authToken => $callBackAuthToken,
                self::$callbackPrefix => $this->urlBuilder->getUrl('vipps/payment/callback'),
                self::$fallBack => $this->urlBuilder->getUrl(
                    'vipps/payment/fallback',
                    [
                        'access_token' => $fallBackAuthToken,
                        'order_id' => $quote->getReservedOrderId()
                    ]
                ),
                self::$consentRemovalPrefix => $this->urlBuilder->getUrl('vipps/payment/consentRemoval'),
                self::$isApp => false,
                self::PAYMENT_TYPE => $buildSubject[self::PAYMENT_TYPE],
            ]
        ];

        if ($buildSubject[self::PAYMENT_TYPE] == self::EXPRESS_CHECKOUT) {
            $merchantInfo[self::$merchantInfo][self::$shippingDetailsPrefix] =  $this->urlBuilder->getUrl(
                'vipps/payment/shippingDetails'
            );
        }
        return $merchantInfo;
    }

    /**
     * Method to generate access token.
     *
     * @return string
     */
    private function generateAuthToken()
    {
        try {
            $randomStr = random_bytes(16);
        } catch (\Exception $e) {
            $randomStr = uniqid('', true);
        }
        return bin2hex($randomStr);
    }
}
