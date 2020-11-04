<?php
/**
 * Copyright 2020 Vipps
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
declare(strict_types=1);

namespace Vipps\Payment\Controller\Payment;

use Laminas\Http\Response;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Result\Layout;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Gateway\Transaction\ShippingDetails as TransactionShippingDetails;
use Vipps\Payment\Model\Gdpr\Compliance;
use Vipps\Payment\Model\Quote\AddressUpdater;
use Vipps\Payment\Model\Quote\ShippingMethodValidator;
use Vipps\Payment\Model\QuoteLocator;

/**
 * Class ShippingDetails
 * @package Vipps\Payment\Controller\Payment
 */
class ShippingDetails implements ActionInterface, CsrfAwareActionInterface
{
    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Compliance
     */
    private $gdprCompliance;

    /**
     * @var QuoteLocator
     */
    private $quoteLocator;

    /**
     * @var LoggerInterface
     */
    private $logger;

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
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var ShippingMethodValidator
     */
    private $shippingMethodValidator;

    /**
     * ShippingDetails constructor.
     *
     * @param ResultFactory $resultFactory
     * @param RequestInterface $request
     * @param QuoteLocator $quoteLocator
     * @param Compliance $compliance
     * @param Json $serializer
     * @param ShipmentEstimationInterface $shipmentEstimation
     * @param AddressInterfaceFactory $addressFactory
     * @param AddressUpdater $addressUpdater
     * @param ShippingMethodValidator $shippingMethodValidator
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResultFactory $resultFactory,
        RequestInterface $request,
        QuoteLocator $quoteLocator,
        Compliance $compliance,
        Json $serializer,
        ShipmentEstimationInterface $shipmentEstimation,
        AddressInterfaceFactory $addressFactory,
        AddressUpdater $addressUpdater,
        ShippingMethodValidator $shippingMethodValidator,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        $this->resultFactory = $resultFactory;
        $this->request = $request;
        $this->quoteLocator = $quoteLocator;
        $this->serializer = $serializer;
        $this->shipmentEstimation = $shipmentEstimation;
        $this->addressFactory = $addressFactory;
        $this->addressUpdater = $addressUpdater;
        $this->shippingMethodValidator = $shippingMethodValidator;
        $this->gdprCompliance = $compliance;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    /**
     * @return ResponseInterface|ResultInterface|Layout
     */
    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $vippsAddress = $this->serializer->unserialize($this->request->getContent());
            $address = $this->addressFactory->create();
            $address->addData([
                'postcode' => $vippsAddress['postCode'],
                'street' => $vippsAddress['addressLine1'] . PHP_EOL . $vippsAddress['addressLine2'],
                'address_type' => 'shipping',
                'city' => $vippsAddress['city'],
                'country_id' => TransactionShippingDetails::NORWEGIAN_COUNTRY_ID
            ]);

            $reservedOrderId = $this->getReservedOrderId();
            $this->logger->critical($reservedOrderId);
            $quote = $this->getQuote($reservedOrderId);
            /**
             * As Quote is deactivated, so we need to activate it for estimating shipping methods
             */
            $this->addressUpdater->fromSourceAddress($quote, $address);
            $quote->setIsActive(true);
            $this->cartRepository->save($quote);
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

            $result->setHttpResponseCode(Response::STATUS_CODE_200);
            $result->setData($responseData);
        } catch (LocalizedException $e) {
            $this->logger->critical($this->enlargeMessage($e));
            $result->setHttpResponseCode(Response::STATUS_CODE_500);
            $result->setData([
                'status' => Response::STATUS_CODE_500,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->critical($this->enlargeMessage($e));
            $result->setHttpResponseCode(Response::STATUS_CODE_500);
            $result->setData([
                'status' => Response::STATUS_CODE_500,
                'message' => __('An error occurred during Shipping Details processing.')
            ]);
        } finally {
            $compliantString = $this->gdprCompliance->process($this->request->getContent());
            $this->logger->debug($compliantString);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param RequestInterface $request
     *
     * @return null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
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
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param $e \Exception
     *
     * @return string
     */
    private function enlargeMessage($e): string
    {
        $trace = $e->getTraceAsString();
        $message = $e->getMessage();

        return "Exception message: $message. Stack Trace $trace";
    }

    /**
     * Get reserved order id from request url
     *
     * @return int|null|string
     */
    private function getReservedOrderId()
    {
        $params = $this->request->getParams();
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
}
