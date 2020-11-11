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

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\Gateway\Command\PaymentDetailsProvider;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\Model\Exception\AcquireLockException;
use Vipps\Payment\Model\Gdpr\Compliance;
use Vipps\Payment\Model\TransactionProcessor;
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

            // main transaction process
            $transaction = $this->transactionProcessor->process($vippsQuote);
            $resultRedirect = $this->prepareResponse($resultRedirect, $transaction);
        } catch (LocalizedException $e) {
            $this->logger->critical($this->enlargeMessage($e));
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($this->enlargeMessage($e));
            $this->messageManager->addErrorMessage(
                __('A server error stopped your transaction from being processed.'
                    . ' Please contact to store administrator.')
            );
        } finally {
            $this->storeLastOrderOrRestoreQuote();
            if (isset($e)) {
                if ($this->getVippsQuote()->getOrderId()) {
                    $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
                } else {
                    $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
                }
            }
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
     * Method to update Checkout session with Last Placed Order
     * Or restore quote if order was not placed (ex. Express Checkout)
     */
    private function storeLastOrderOrRestoreQuote()
    {
        $vippsQuote = $this->getVippsQuote(true);
        if ($vippsQuote->getOrderId()) {
            $this->checkoutSession
                ->clearStorage()
                ->setLastQuoteId($vippsQuote->getQuoteId())
                ->setLastSuccessQuoteId($vippsQuote->getQuoteId())
                ->setLastOrderId($vippsQuote->getOrderId())
                ->setLastRealOrderId($vippsQuote->getReservedOrderId())
                ->setLastOrderStatus(
                    $this->orderManagement->getStatus($vippsQuote->getOrderId())
                );
        } else {
            $quote = $this->cartRepository->get($vippsQuote->getQuoteId());

            /** @var Quote $quote */
            $quote->setIsActive(true);
            $quote->setReservedOrderId(null);

            $this->cartRepository->save($quote);
            $this->checkoutSession->replaceQuote($quote);
        }
    }

    /**
     * @param Redirect $resultRedirect
     * @param Transaction $transaction
     *
     * @return Redirect
     * @throws \Exception
     */
    private function prepareResponse(Redirect $resultRedirect, Transaction $transaction)
    {
        if ($transaction->transactionWasCancelled()) {
            $this->messageManager->addWarningMessage(__('Your order was cancelled in Vipps.'));
        } elseif ($transaction->isTransactionReserved()) {
            return $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
        } elseif ($transaction->isTransactionExpired()) {
            $this->messageManager->addErrorMessage(
                __('Transaction was expired. Please, place your order again')
            );
        } else {
            $this->messageManager->addWarningMessage(
                __('We have not received a confirmation that order was reserved. It will be checked later again.')
            );
        }

        if ($this->getVippsQuote()->getOrderId()) {
            $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
        } else {
            $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
        }

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
        $quoteId = $this->checkoutSession->getQuoteId();
        $trace = $e->getTraceAsString();
        $message = $e->getMessage();

        return "QuoteID: $quoteId. Exception message: $message. Stack Trace $trace";
    }
}
