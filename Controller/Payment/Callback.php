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
    Controller\ResultFactory,
    App\Action\Action,
    App\Action\Context,
    Controller\ResultInterface,
    App\ResponseInterface,
    Serialize\Serializer\Json
};
use Vipps\Payment\{
    Gateway\Request\Initiate\MerchantDataBuilder,
    Gateway\Transaction\TransactionBuilder,
    Model\OrderPlace,
    Model\QuoteLocator,
    Model\Gdpr\Compliance
};
use Magento\Quote\{
    Api\Data\CartInterface, Model\Quote
};
use Zend\Http\Response as ZendResponse;
use Psr\Log\LoggerInterface;

/**
 * Class Callback
 * @package Vipps\Payment\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Callback extends Action
{
    /**
     * @var OrderPlace
     */
    private $orderPlace;

    /**
     * @var QuoteLocator
     */
    private $quoteLocator;

    /**
     * @var Json
     */
    private $jsonDecoder;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CartInterface
     */
    private $quote;
    /**
     * @var Compliance
     */
    private $gdprCompliance;

    /**
     * Callback constructor.
     *
     * @param Context $context
     * @param OrderPlace $orderManagement
     * @param QuoteLocator $quoteLocator
     * @param Json $jsonDecoder
     * @param TransactionBuilder $transactionBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        OrderPlace $orderManagement,
        QuoteLocator $quoteLocator,
        Json $jsonDecoder,
        TransactionBuilder $transactionBuilder,
        Compliance $compliance,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->orderPlace = $orderManagement;
        $this->quoteLocator = $quoteLocator;
        $this->jsonDecoder = $jsonDecoder;
        $this->transactionBuilder = $transactionBuilder;
        $this->logger = $logger;
        $this->gdprCompliance = $compliance;
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
            $requestData = $this->jsonDecoder->unserialize($this->getRequest()->getContent());

            $this->authorize($requestData);

            $transaction = $this->transactionBuilder->setData($requestData)->build();
            $this->orderPlace->execute($this->getQuote($requestData), $transaction);

            /** @var Json $result */
            $result->setHttpResponseCode(ZendResponse::STATUS_CODE_200);
            $result->setData(['status' => ZendResponse::STATUS_CODE_200, 'message' => 'success']);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $result->setHttpResponseCode(ZendResponse::STATUS_CODE_500);
            $result->setData([
                'status' => ZendResponse::STATUS_CODE_500,
                'message' => __('An error occurred during callback processing.')
            ]);
        } finally {
            $compliant = $this->gdprCompliance->process($this->getRequest()->getContent());
            $this->logger->debug($compliant);
        }
        return $result;
    }

    /**
     * @param $requestData
     *
     * @return bool
     * @throws \Exception
     */
    private function authorize($requestData)
    {
        if (!$this->isValid($requestData)) {
            throw new \Exception(__('Invalid request parameters'), 400); //@codingStandardsIgnoreLine
        }
        if (!$this->isAuthorized($requestData)) {
            throw new \Exception(__('Invalid request'), 401); //@codingStandardsIgnoreLine
        }
        return true;
    }

    /**
     * Method to validate request body parameters.
     *
     * @param array $requestData
     *
     * @return bool
     */
    private function isValid($requestData): bool
    {
        return array_key_exists('orderId', $requestData)
            && array_key_exists('transactionInfo', $requestData)
            && array_key_exists('status', $requestData['transactionInfo'])
            && $this->getRequest()->getHeader('authorization');
    }

    /**
     * Return order id
     *
     * @param $requestData
     *
     * @return string|null
     */
    private function getOrderId($requestData)
    {
        return $requestData['orderId'] ?? null;
    }

    /**
     * Retrieve a quote from repository based on request parameter order id
     *
     * @param $requestData
     *
     * @return bool|CartInterface|Quote
     */
    private function getQuote($requestData)
    {
        if (null === $this->quote) {
            $this->quote = $this->quoteLocator->get($this->getOrderId($requestData)) ?: false;
        }
        return $this->quote;
    }

    /**
     * @param array $requestData
     *
     * @return bool
     */
    private function isAuthorized($requestData): bool
    {
        $quote = $this->getQuote($requestData);
        if ($quote) {
            $additionalInfo = $quote->getPayment()->getAdditionalInformation();
            $authToken = $additionalInfo[MerchantDataBuilder::MERCHANT_AUTH_TOKEN] ?? null;

            if ($authToken === $this->getRequest()->getHeader('authorization')) {
                return true;
            }
        }
        return false;
    }
}
