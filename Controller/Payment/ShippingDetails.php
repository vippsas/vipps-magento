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
namespace Vipps\Payment\Controller\Payment;

use Magento\Framework\{
    Controller\ResultFactory, Exception\LocalizedException, Serialize\Serializer\Json,
    Controller\ResultInterface, App\ResponseInterface, App\Action\Action, App\Action\Context
};
use Magento\Quote\Api\{
    CartRepositoryInterface,ShipmentEstimationInterface, Data\AddressInterfaceFactory
};
use Vipps\Payment\Gateway\Transaction\ShippingDetails as TransactionShippingDetails;
use Vipps\Payment\Model\OrderManagement;
use Zend\Http\Response as ZendResponse;
use Psr\Log\LoggerInterface;

/**
 * Class ShippingDetails
 * @package Vipps\Payment\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ShippingDetails extends Action
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var OrderManagement
     */
    private $orderManagement;

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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ShippingDetails constructor.
     *
     * @param Context $context
     * @param CartRepositoryInterface $cartRepository
     * @param OrderManagement $orderManagement
     * @param ShipmentEstimationInterface $shipmentEstimation
     * @param AddressInterfaceFactory $addressFactory
     * @param Json $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CartRepositoryInterface $cartRepository,
        OrderManagement $orderManagement,
        ShipmentEstimationInterface $shipmentEstimation,
        AddressInterfaceFactory $addressFactory,
        Json $serializer,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->cartRepository = $cartRepository;
        $this->orderManagement = $orderManagement;
        $this->serializer = $serializer;
        $this->shipmentEstimation = $shipmentEstimation;
        $this->addressFactory = $addressFactory;
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
            $params = $this->_request->getParams();
            next($params);
            $reservedOrderId = key($params);
            $quote = $this->orderManagement->getQuoteByReservedOrderId($reservedOrderId);
            $vippsAddress = $this->serializer->unserialize($this->_request->getContent());
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
            $quote->setIsActive(true);
            $shippingMethods = $this->shipmentEstimation->estimateByExtendedAddress($quote->getId(), $address);
            $responseData = [
                'addressId' => $vippsAddress['addressId'],
                'orderId' => $reservedOrderId,
                'shippingDetails' => []
            ];
            foreach ($shippingMethods as $key => $shippingMethod) {
                $responseData['shippingDetails'][] = [
                    'isDefault' => 'N',
                    'priority' => $key,
                    'shippingCost' => $shippingMethod->getAmount(),
                    'shippingMethod' => $shippingMethod->getMethodCode(),
                    'shippingMethodId' => $shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode(),
                ];
            }
            $result->setHttpResponseCode(ZendResponse::STATUS_CODE_200);
            $result->setData($responseData);
        } catch (LocalizedException $e) {
            $result->setHttpResponseCode(ZendResponse::STATUS_CODE_500);
            $result->setData([
                'status' => ZendResponse::STATUS_CODE_500,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $result->setHttpResponseCode(ZendResponse::STATUS_CODE_500);
            $result->setData([
                'status' => ZendResponse::STATUS_CODE_500,
                'message' => __('An error occurred during Shipping Details processing.')
            ]);
        }
        return $result;
    }
}
