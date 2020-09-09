<?php
/**
 * Copyright Vipps
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

namespace Vipps\Payment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Gateway\Transaction\ShippingDetails as TransactionShippingDetails;
use Vipps\Payment\Model\Gdpr\Compliance;
use Vipps\Payment\Model\Quote\AddressUpdater;
use Vipps\Payment\Model\QuoteLocator;
use Vipps\Payment\Model\Quote\ShippingMethodValidator;
use Zend\Http\Response as ZendResponse;

/**
 * Class ShippingDetails
 * @package Vipps\Payment\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ShippingDetails extends Action implements CsrfAwareActionInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var QuoteLocator
     */
    private $quoteLocator;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var ShipmentEstimationInterface
     */
    private $shipmentEstimation;

    /**
     * @var AddressInterfaceFactory
     */
    private $addressFactory;

    /**
     * @var AddressUpdater
     */
    private $addressUpdater;

    /**
     * @var ShippingMethodValidator
     */
    private $shippingMethodValidator;

    /**
     * @var Compliance
     */
    private $gdprCompliance;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ShippingDetails constructor.
     *
     * @param Context $context
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteLocator $quoteLocator
     * @param Json $serializer
     * @param ShipmentEstimationInterface $shipmentEstimation
     * @param AddressInterfaceFactory $addressFactory
     * @param AddressUpdater $addressUpdater
     * @param ShippingMethodValidator $shippingMethodValidator
     * @param Compliance $compliance
     * @param LoggerInterface $logger
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        CartRepositoryInterface $cartRepository,
        QuoteLocator $quoteLocator,
        Json $serializer,
        ShipmentEstimationInterface $shipmentEstimation,
        AddressInterfaceFactory $addressFactory,
        AddressUpdater $addressUpdater,
        ShippingMethodValidator $shippingMethodValidator,
        Compliance $compliance,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->cartRepository = $cartRepository;
        $this->quoteLocator = $quoteLocator;
        $this->serializer = $serializer;
        $this->shipmentEstimation = $shipmentEstimation;
        $this->addressFactory = $addressFactory;
        $this->addressUpdater = $addressUpdater;
        $this->shippingMethodValidator = $shippingMethodValidator;
        $this->gdprCompliance = $compliance;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $reservedOrderId = $this->getReservedOrderId();
            $quote = $this->getQuote($reservedOrderId);
            $vippsAddress = $this->serializer->unserialize($this->getRequest()->getContent());
            $address = $this->addressFactory->create();
            $address->addData([
                'postcode' => $vippsAddress['postCode'],
                'street' => $vippsAddress['addressLine1'] . PHP_EOL . $vippsAddress['addressLine2'],
                'address_type' => 'shipping',
                'city' => $vippsAddress['city'],
                'country_id' => TransactionShippingDetails::NORWEGIAN_COUNTRY_ID
            ]);
            /**
             * As Quote is deactivated, so we need to activate it for estimating shipping methods
             */
            $quote = $this->cartRepository->get($quote->getId());
            $this->addressUpdater->fromSourceAddress($quote, $address);
            $quote->setIsActive(true);
            $shippingMethods = $this->shipmentEstimation->estimateByExtendedAddress($quote->getId(), $address);
            $responseData = [
                'addressId' => $vippsAddress['addressId'],
                'orderId' => $reservedOrderId,
                'shippingDetails' => []
            ];
            foreach ($shippingMethods as $key => $shippingMethod) {
                $methodFullCode = $shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode();
                if (!$this->shippingMethodValidator->isValid($methodFullCode)) {
                    continue;
                }

                $responseData['shippingDetails'][] = [
                    'isDefault' => 'N',
                    'priority' => $key,
                    'shippingCost' => $shippingMethod->getAmount(),
                    'shippingMethod' => $shippingMethod->getMethodTitle(),
                    'shippingMethodId' => $methodFullCode,
                ];
            }
            $result->setHttpResponseCode(ZendResponse::STATUS_CODE_200);
            $result->setData($responseData);
        } catch (LocalizedException $e) {
            $this->logger->critical($this->enlargeMessage($e));
            $result->setHttpResponseCode(ZendResponse::STATUS_CODE_500);
            $result->setData([
                'status' => ZendResponse::STATUS_CODE_500,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->critical($this->enlargeMessage($e));
            $result->setHttpResponseCode(ZendResponse::STATUS_CODE_500);
            $result->setData([
                'status' => ZendResponse::STATUS_CODE_500,
                'message' => __('An error occurred during Shipping Details processing.')
            ]);
        } finally {
            $compliantString = $this->gdprCompliance->process($this->getRequest()->getContent());
            $this->logger->debug($compliantString);
        }
        return $result;
    }

    /**
     * Get reserved order id from request url
     *
     * @return int|null|string
     */
    private function getReservedOrderId()
    {
        $params = $this->getRequest()->getParams();
        next($params);

        return key($params);
    }

    /**
     * Retrieve quote object
     *
     * @param $reservedOrderId
     *
     * @return CartInterface|Quote
     * @throws LocalizedException
     */
    private function getQuote($reservedOrderId)
    {
        $quote = $this->quoteLocator->get($reservedOrderId);
        if (!$quote) {
            throw new LocalizedException(__('Requested quote not found'));
        }

        return $quote;
    }

    /**
     * {@inheritdoc}
     *
     * @param RequestInterface $request
     *
     * @return null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException //@codingStandardsIgnoreLine
    {
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @param RequestInterface $request
     *
     * @return bool
     */
    public function validateForCsrf(RequestInterface $request): ?bool //@codingStandardsIgnoreLine
    {
        return true;
    }

    /**
     * @param $e \Exception
     * @return string
     */
    private function enlargeMessage($e): string
    {
        return 'Reserved Order id: ' . ($this->getReservedOrderId() ?? 'Missing') .
            ' . Exception message: ' . $e->getMessage();
    }
}
