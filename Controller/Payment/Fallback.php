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
    Controller\ResultFactory, Controller\ResultInterface, Exception\LocalizedException,
    Session\SessionManagerInterface, Controller\Result\Redirect, App\Action\Action, App\Action\Context,
    App\ResponseInterface
};
use Vipps\Payment\{
    Api\CommandManagerInterface, Gateway\Request\Initiate\MerchantDataBuilder, Model\OrderManagement,
    Gateway\Exception\VippsException, Gateway\Command\PaymentDetailsProvider,
    Gateway\Transaction\TransactionBuilder
};
use Magento\Quote\{Api\Data\CartInterface, Api\CartRepositoryInterface};
use Magento\Sales\Api\Data\OrderInterface;
use Zend\Http\Response as ZendResponse;
use Psr\Log\LoggerInterface;

/**
 * Class Initiate
 * @package Vipps\Payment\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Fallback extends Action
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var SessionManagerInterface
     */
    private $checkoutSession;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * @var PaymentDetailsProvider
     */
    private $paymentDetailsProvider;

    /**
     * @var OrderManagement
     */
    private $orderManagement;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Fallback constructor.
     *
     * @param Context $context
     * @param CommandManagerInterface $commandManager
     * @param SessionManagerInterface $checkoutSession
     * @param TransactionBuilder $transactionBuilder
     * @param PaymentDetailsProvider $paymentDetailsProvider
     * @param OrderManagement $orderManagement
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CommandManagerInterface $commandManager,
        SessionManagerInterface $checkoutSession,
        TransactionBuilder $transactionBuilder,
        PaymentDetailsProvider $paymentDetailsProvider,
        OrderManagement $orderManagement,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->commandManager = $commandManager;
        $this->checkoutSession = $checkoutSession;
        $this->transactionBuilder = $transactionBuilder;
        $this->paymentDetailsProvider = $paymentDetailsProvider;
        $this->orderManagement = $orderManagement;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $reservedOrderId = $this->getRequest()->getParam('orderId');
            $quote = $this->orderManagement->getQuoteByReservedOrderId($reservedOrderId);
            if (!$this->isAuthorized($quote)) {
                throw new LocalizedException(__('Bad Request'));
            }
            $order = $this->orderManagement->getOrderByIncrementId($reservedOrderId);
            if (!$order) {
                $response = $this->paymentDetailsProvider->get(['orderId' => $reservedOrderId]);
                if (!$response) {
                    throw new LocalizedException(__('An error occurred during order creation.'));
                }
                $transaction = $this->transactionBuilder->setData($response)->build();
                $this->orderManagement->place($reservedOrderId, $transaction);
            } else {
                $this->updateCheckoutSession($quote, $order);
            }
            /** @var ZendResponse $result */
            $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
        } catch (VippsException $e) {
            $this->logger->critical($e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
        } catch (LocalizedException $e) {
            $this->logger->critical($e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $this->messageManager->addErrorMessage(__('An error occurred during payment status update.'));
            $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
        }
        return $resultRedirect;
    }

    /**
     * Method to update Checkout session for success page when order was placed with Callback Controller.
     *
     * @param CartInterface $quote
     * @param OrderInterface $order
     */
    private function updateCheckoutSession(CartInterface $quote, OrderInterface $order)
    {
        $this->checkoutSession->setLastQuoteId($quote->getId());
        $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
        $this->checkoutSession->setLastOrderId($order->getId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderStatus($order->getStatus());
    }

    /**
     * @param $quote
     *
     * @return bool
     */
    private function isAuthorized($quote): bool
    {
        $additionalInfo = $quote->getPayment()->getAdditionalInformation();
        $fallbackAuthToken = $additionalInfo[MerchantDataBuilder::FALLBACK_AUTH_TOKEN] ?? null;

        $returnedToken = $this->getRequest()->getParam('accessToken');

        if (!$returnedToken || $fallbackAuthToken !== $returnedToken) {
            return false;
        }
        // clear fallback auth token when success
        $additionalInfo[MerchantDataBuilder::FALLBACK_AUTH_TOKEN] = null;
        $quote->getPayment()->setAdditionalInformation($additionalInfo);
        $this->cartRepository->save($quote);

        return true;
    }
}
