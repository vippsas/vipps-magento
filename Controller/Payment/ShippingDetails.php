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

use Magento\Framework\{
    Controller\ResultFactory, Exception\LocalizedException, Serialize\Serializer\Json,
    Controller\ResultInterface, App\ResponseInterface, App\Action\Action, App\Action\Context
};
use Magento\Quote\Api\{
    CartRepositoryInterface, Data\CartInterface, ShipmentEstimationInterface, Data\AddressInterfaceFactory
};
use Magento\Quote\Model\Quote;
use Vipps\Payment\Model\Gdpr\Compliance;
use Vipps\Payment\Gateway\Transaction\ShippingDetails as TransactionShippingDetails;
use Vipps\Payment\Model\{Quote\ShippingMethodValidator, QuoteLocator, Quote\AddressUpdater};
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Compliance
     */
    private $gdprCompliance;

    /**
     * @var AddressUpdater
     */
    private $addressUpdater;
    /**
     * @var ShippingMethodValidator
     */
    private $shippingMethodValidator;

    /**
     * ShippingDetails constructor.
     *
     * @param Context $context
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteLocator $quoteLocator
     * @param ShipmentEstimationInterface $shipmentEstimation
     * @param AddressInterfaceFactory $addressFactory
     * @param AddressUpdater $addressUpdater
     * @param Compliance $compliance
     * @param Json $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CartRepositoryInterface $cartRepository,
        QuoteLocator $quoteLocator,
        ShipmentEstimationInterface $shipmentEstimation,
        AddressInterfaceFactory $addressFactory,
        AddressUpdater $addressUpdater,
        ShippingMethodValidator $shippingMethodValidator,
        Compliance $compliance,
        Json $serializer,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->cartRepository = $cartRepository;
        $this->quoteLocator = $quoteLocator;
        $this->serializer = $serializer;
        $this->shipmentEstimation = $shipmentEstimation;
        $this->addressFactory = $addressFactory;
        $this->logger = $logger;
        $this->addressUpdater = $addressUpdater;
        $this->gdprCompliance = $compliance;
        $this->shippingMethodValidator = $shippingMethodValidator;
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
            $address = $quote->getShippingAddress();
            $quote->setIsActive(true);
            $shippingMethods = $this->shipmentEstimation->estimateByExtendedAddress($quote->getId(), $address);
            $responseData = [
                'addressId' => 1,
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
            $this->logger->critical($e->getMessage());
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
        $reservedOrderId = key($params);

        return $reservedOrderId;
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
