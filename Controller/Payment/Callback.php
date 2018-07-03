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
    Controller\ResultFactory, App\Action\Action, App\Action\Context, Serialize\Serializer\Json,
    Session\SessionManagerInterface, Controller\ResultInterface, App\ResponseInterface
};
use Vipps\Payment\{
    Api\CommandManagerInterface, Gateway\Request\Initiate\MerchantDataBuilder, Gateway\Transaction\TransactionBuilder,
    Model\OrderManagement
};
use Magento\Quote\{Api\CartRepositoryInterface, Model\Quote};
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
     * @var SessionManagerInterface
     */
    private $checkoutSession;

    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

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
     * Callback constructor.
     *
     * @param Context $context
     * @param SessionManagerInterface $checkoutSession
     * @param CommandManagerInterface $commandManager
     * @param CartRepositoryInterface $cartRepository
     * @param OrderManagement $orderManagement
     * @param Json $jsonDecoder
     * @param TransactionBuilder $transactionBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        SessionManagerInterface $checkoutSession,
        CommandManagerInterface $commandManager,
        CartRepositoryInterface $cartRepository,
        OrderManagement $orderManagement,
        Json $jsonDecoder,
        TransactionBuilder $transactionBuilder,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->commandManager = $commandManager;
        $this->cartRepository = $cartRepository;
        $this->orderManagement = $orderManagement;
        $this->jsonDecoder = $jsonDecoder;
        $this->transactionBuilder = $transactionBuilder;
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
            $requestContent = $this->getRequest()->getContent();
            $requestData = $this->jsonDecoder->unserialize($requestContent);

            if (!$this->isValid($requestData)) {
                throw new \Exception(__('Invalid request parameters'), 400); //@codingStandardsIgnoreLine
            }

            if (!$this->isAuthorized($requestData)) {
                throw new \Exception(__('Invalid request'), 401); //@codingStandardsIgnoreLine
            }

            // create transaction object
            $transaction = $this->transactionBuilder->setData($requestData)->build();

            // place order if not exist in Magento and update order status based on transaction info
            $this->orderManagement->place($this->getOrderId($requestData), $transaction);

            $result->setHttpResponseCode(ZendResponse::STATUS_CODE_200);
            $result->setData([
                'status' => ZendResponse::STATUS_CODE_200,
                'message' => 'success'
                ]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $result->setHttpResponseCode(ZendResponse::STATUS_CODE_500);
            $result->setData([
                'status' => ZendResponse::STATUS_CODE_500,
                'message' => __('An error occurred during callback processing.')
            ]);
        }
        return $result;
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
     * @return Quote|mixed
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    private function getQuote($requestData)
    {
        return $this->orderManagement->getQuoteByReservedOrderId($this->getOrderId($requestData));
    }

    /**
     * @param $requestData
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function isAuthorized($requestData): bool
    {
        $quote = $this->getQuote($requestData);
        if ($quote) {
            $additionalInfo = $quote->getPayment()->getAdditionalInformation();
            $authToken = $additionalInfo[MerchantDataBuilder::MERCHANT_AUTH_TOKEN] ?? null;

            if ($authToken !== $this->getRequest()->getHeader('authorization')) {
                return false;
            }

            // clear merchant auth token when success
            $additionalInfo[MerchantDataBuilder::MERCHANT_AUTH_TOKEN] = null;
            $quote->getPayment()->setAdditionalInformation($additionalInfo);
            $this->cartRepository->save($quote);

            return true;
        }

        return false;
    }
}
