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

namespace Vipps\Payment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\Gateway\Command\PaymentDetailsProvider;
use Vipps\Payment\Model\Gdpr\Compliance;
use Vipps\Payment\Model\TransactionProcessor;
use Laminas\Http\Response as Response;

/**
 * Class Callback
 * @package Vipps\Payment\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Callback extends Action implements CsrfAwareActionInterface
{
    /**
     * @var TransactionProcessor
     */
    private $transactionProcessor;

    /**
     * @var QuoteRepositoryInterface
     */
    private $vippsQuoteRepository;

    /**
     * @var Json
     */
    private $jsonDecoder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var QuoteInterface
     */
    private $vippsQuote;

    /**
     * @var PaymentDetailsProvider
     */
    private $paymentDetailsProvider;

    /**
     * @var Compliance
     */
    private $gdprCompliance;

    /**
     * Callback constructor.
     *
     * @param Context $context
     * @param TransactionProcessor $orderManagement
     * @param QuoteRepositoryInterface $vippsQuoteRepository
     * @param PaymentDetailsProvider $paymentDetailsProvider
     * @param Json $jsonDecoder
     * @param Compliance $compliance
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        TransactionProcessor $orderManagement,
        QuoteRepositoryInterface $vippsQuoteRepository,
        PaymentDetailsProvider $paymentDetailsProvider,
        Json $jsonDecoder,
        Compliance $compliance,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->transactionProcessor = $orderManagement;
        $this->vippsQuoteRepository = $vippsQuoteRepository;
        $this->paymentDetailsProvider = $paymentDetailsProvider;
        $this->jsonDecoder = $jsonDecoder;
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
            $requestData = $this->jsonDecoder->unserialize($this->getRequest()->getContent());

            $this->authorize($requestData);

            $transaction = $this->getPaymentDetails($requestData);
            $this->transactionProcessor->process($this->getVippsQuote($requestData), $transaction);

            /** @var Json $result */
            $result->setHttpResponseCode(Response::STATUS_CODE_200);
            $result->setData(['status' => Response::STATUS_CODE_200, 'message' => 'success']);
        } catch (\Exception $e) {
            $orderId = $requestData['orderId'] ?? 'Missing';
            $message = 'OrderID: ' . $orderId . ' . Exception message: ' . $e->getMessage();
            $this->logger->critical($message);
            $result->setHttpResponseCode(Response::STATUS_CODE_500);
            $result->setData([
                'status' => Response::STATUS_CODE_500,
                'message' => $message
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
     * @return \Vipps\Payment\Gateway\Transaction\Transaction
     * @throws \Vipps\Payment\Gateway\Exception\VippsException
     */
    private function getPaymentDetails($requestData)
    {
        return $this->paymentDetailsProvider->get($requestData['orderId']);
    }

    /**
     * @param array $requestData
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
     * @param $requestData
     *
     * @return QuoteInterface
     * @throws NoSuchEntityException
     */
    private function getVippsQuote($requestData)
    {
        if (null === $this->vippsQuote) {
            $this->vippsQuote = $this->vippsQuoteRepository->loadByOrderId($requestData['orderId']);
        }
        return $this->vippsQuote;
    }

    /**
     * @param $requestData
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    private function isAuthorized($requestData): bool
    {
        $vippsQuote = $this->getVippsQuote($requestData);
        if ($vippsQuote) {
            if ($vippsQuote->getAuthToken() === $this->getRequest()->getHeader('authorization')) {
                return true;
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException //@codingStandardsIgnoreLine
    {
        return null;
    }

    /**
     * {@inheritdoc}
     * @param RequestInterface $request
     *
     * @return bool
     */
    public function validateForCsrf(RequestInterface $request): ?bool //@codingStandardsIgnoreLine
    {
        return true;
    }
}
