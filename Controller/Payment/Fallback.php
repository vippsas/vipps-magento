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

use Magento\Checkout\Model\Session;
use Magento\Framework\{App\Action\Action,
    App\Action\Context,
    App\CsrfAwareActionInterface,
    App\Request\InvalidRequestException,
    App\RequestInterface,
    App\ResponseInterface,
    Controller\Result\Redirect,
    Controller\ResultFactory,
    Controller\ResultInterface,
    Exception\CouldNotSaveException,
    Exception\LocalizedException,
    Exception\NoSuchEntityException,
    Session\SessionManagerInterface};
use Magento\Quote\{Api\CartRepositoryInterface, Model\Quote};
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\{Api\Data\QuoteInterface,
    Api\QuoteRepositoryInterface,
    Gateway\Command\PaymentDetailsProvider,
    Gateway\Transaction\Transaction,
    Model\Gdpr\Compliance,
    Model\TransactionProcessor};
use Vipps\Payment\Gateway\Exception\VippsException;

/**
 * Class Fallback
 * @package Vipps\Payment\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Fallback extends Action implements CsrfAwareActionInterface
{
    /**
     * @var PaymentDetailsProvider
     */
    private $paymentDetailsProvider;

    /**
     * @var SessionManagerInterface|Session
     */
    private $checkoutSession;

    /**
     * @var TransactionProcessor
     */
    private $transactionProcessor;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var QuoteRepositoryInterface
     */
    private $vippsQuoteRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Compliance
     */
    private $gdprCompliance;

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var QuoteInterface
     */
    private $vippsQuote;

    /**
     * Fallback constructor.
     *
     * @param Context $context
     * @param PaymentDetailsProvider $paymentDetailsProvider
     * @param SessionManagerInterface $checkoutSession
     * @param TransactionProcessor $transactionProcessor
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteRepositoryInterface $vippsQuoteRepository
     * @param OrderManagementInterface $orderManagement
     * @param Compliance $compliance
     * @param LoggerInterface $logger
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        PaymentDetailsProvider $paymentDetailsProvider,
        SessionManagerInterface $checkoutSession,
        TransactionProcessor $transactionProcessor,
        CartRepositoryInterface $cartRepository,
        QuoteRepositoryInterface $vippsQuoteRepository,
        OrderManagementInterface $orderManagement,
        Compliance $compliance,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->paymentDetailsProvider = $paymentDetailsProvider;
        $this->checkoutSession = $checkoutSession;
        $this->transactionProcessor = $transactionProcessor;
        $this->cartRepository = $cartRepository;
        $this->vippsQuoteRepository = $vippsQuoteRepository;
        $this->gdprCompliance = $compliance;
        $this->orderManagement = $orderManagement;
        $this->logger = $logger;
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     * @throws CouldNotSaveException
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $this->authorize();

            $vippsQuote = $this->getVippsQuote();
            $transaction = $this->getPaymentDetails();

            // main transaction process
            $this->transactionProcessor->process($vippsQuote, $transaction);

            $this->prepareResponse($resultRedirect, $transaction);
        } catch (LocalizedException $e) {
            $this->logger->critical($this->enlargeMessage($e));
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
        } catch (\Exception $e) {
            $this->logger->critical($this->enlargeMessage($e));
            $this->messageManager->addErrorMessage(
                __('A server error stopped your transaction from being processed.'
                    . ' Please contact to store administrator.')
            );
            $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
        } finally {
            $this->logger->debug($this->getRequest()->getRequestString());
        }
        return $resultRedirect;
    }

    /**
     * Request authorization process
     *
     * @throws LocalizedException
     */
    private function authorize()
    {
        if (!$this->getRequest()->getParam('order_id')
            || !$this->getRequest()->getParam('auth_token')
        ) {
            throw new LocalizedException(__('Invalid request parameters'));
        }

        $vippsQuote = $this->getVippsQuote();
        if ($vippsQuote->getAuthToken() === $this->getRequest()->getParam('auth_token', '')) {
            return true;
        }

        throw new LocalizedException(__('Invalid request'));
    }

    /**
     * @param bool $forceReload
     *
     * @return QuoteInterface
     * @throws NoSuchEntityException
     */
    private function getVippsQuote($forceReload = false)
    {
        if (null === $this->vippsQuote || $forceReload) {
            $this->vippsQuote = $this->vippsQuoteRepository
                ->loadByOrderId($this->getRequest()->getParam('order_id'));
        }
        return $this->vippsQuote;
    }

    /**
     * @return Transaction
     * @throws VippsException
     */
    private function getPaymentDetails()
    {
        return $this->paymentDetailsProvider->get($this->getRequest()->getParam('order_id'));
    }

    /**
     * @param QuoteInterface $vippsQuote
     *
     * @throws NoSuchEntityException
     */
    private function restoreQuote(QuoteInterface $vippsQuote)
    {
        $quote = $this->cartRepository->get($vippsQuote->getQuoteId());

        /** @var Quote $quote */
        $quote->setIsActive(true);
        $quote->setReservedOrderId(null);
        $this->cartRepository->save($quote);

        $this->checkoutSession->setLastQuoteId($quote->getId());
        $this->checkoutSession->replaceQuote($quote);
    }

    /**
     * Method to update Checkout session for success page when order was placed with Callback Controller.
     *
     * @param QuoteInterface $vippsQuote
     */
    private function storeSuccessDataToCheckoutSession(QuoteInterface $vippsQuote)
    {
        $this->checkoutSession->clearStorage();
        $this->checkoutSession->setLastQuoteId($vippsQuote->getQuoteId());
        if ($vippsQuote->getOrderId()) {
            $this->checkoutSession->setLastSuccessQuoteId($vippsQuote->getQuoteId());
            $this->checkoutSession->setLastOrderId($vippsQuote->getOrderId());
            $this->checkoutSession->setLastRealOrderId($vippsQuote->getReservedOrderId());
            $this->checkoutSession->setLastOrderStatus($this->orderManagement->getStatus($vippsQuote->getOrderId()));
        }
    }

    /**
     * @param Redirect $resultRedirect
     * @param Transaction $transaction
     *
     * @return Redirect
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    private function prepareResponse(Redirect $resultRedirect, Transaction $transaction)
    {
        $vippsQuote = $this->getVippsQuote(true);

        if ($transaction->transactionWasCancelled()) {
            $this->restoreQuote($vippsQuote);
            $this->messageManager->addWarningMessage(__('Your order was cancelled in Vipps.'));
            $resultRedirect->setPath('checkout/cart', ['_secure' => true]);

            return $resultRedirect;
        }

        if ($transaction->isTransactionReserved()) {
            $this->storeSuccessDataToCheckoutSession($vippsQuote);
            $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);

            return $resultRedirect;
        }

        if ($transaction->isTransactionExpired()) {
            $this->restoreQuote($vippsQuote);
            $this->messageManager->addErrorMessage(
                __('Transaction was expired. Please, place your order again')
            );
            $resultRedirect->setPath('checkout/cart', ['_secure' => true]);

            return $resultRedirect;
        }

        $this->messageManager->addWarningMessage(
            __('We have not received a confirmation that order was reserved. It will be checked later again.')
        );
        $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);

        return $resultRedirect;
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
     *
     * @return string
     */
    private function enlargeMessage($e): string
    {
        return 'OrderID: ' .
            $this->getRequest()->getParam('order_id', 'Missing') .
            ' . Error Message: ' . $e->getMessage();
    }
}
