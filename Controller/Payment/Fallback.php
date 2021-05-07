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
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\Gateway\Command\PaymentDetailsProvider;
use Vipps\Payment\Gateway\Exception\VippsException;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\Model\Exception\AcquireLockException;
use Vipps\Payment\Model\Gdpr\Compliance;
use Vipps\Payment\Model\TransactionProcessor;

/**
 * Class Fallback
 * @package Vipps\Payment\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Fallback extends Action
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
     * @var ConfigInterface
     */
    private $config;

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
     * @param ConfigInterface $config
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
        LoggerInterface $logger,
        ConfigInterface $config
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
        $this->config = $config;
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     * @throws CouldNotSaveException
     */
    public function execute()
    {
        $transaction = null;
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
            $vippsQuote = $this->getVippsQuote(true);
            $cartPersistence = $this->config->getValue('cancellation_cart_persistence');
            $transactionWasCancelled = $transaction && $transaction->transactionWasCancelled();

            if ($transactionWasCancelled && $cartPersistence) {
                $this->restoreQuote($vippsQuote);
            } elseif ($vippsQuote->getOrderId()) {
                $this->storeLastOrder($vippsQuote);
            }
            if (isset($e)) {
                if (!$cartPersistence && $this->getVippsQuote()->getOrderId()) {
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
    private function storeLastOrder(QuoteInterface $vippsQuote)
    {
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
        }
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
        $this->checkoutSession->replaceQuote($quote);
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
        $this->defineMessage($transaction);
        $this->defineRedirectPath($resultRedirect, $transaction);

        return $resultRedirect;
    }

    /**
     * @param $e \Exception
     *
     * @return string
     */
    private function enlargeMessage($e)
    {
        $quoteId = $this->checkoutSession->getQuoteId();
        $trace = $e->getTraceAsString();
        $message = $e->getMessage();

        return "QuoteID: $quoteId. Exception message: $message. Stack Trace $trace";
    }

    /**
     * @return mixed
     */
    private function isCartPersistent()
    {
        return $this->config->getValue('cancellation_cart_persistence');
    }

    private function defineMessage(Transaction $transaction): void
    {
        if ($transaction->transactionWasCancelled()) {
            $this->messageManager->addWarningMessage(__('Your order was cancelled in Vipps.'));
        } elseif ($transaction->isTransactionReserved() || $transaction->isTransactionCaptured()) {
            //$this->messageManager->addWarningMessage(__('Your order was successfully placed.'));
        } elseif ($transaction->isTransactionExpired()) {
            $this->messageManager->addErrorMessage(
                __('Transaction was expired. Please, place your order again')
            );
        } else {
            $this->messageManager->addWarningMessage(
                __('We have not received a confirmation that order was reserved. It will be checked later again.')
            );
        }
    }

    /**
     * @param Redirect $resultRedirect
     * @param Transaction $transaction
     *
     * @throws NoSuchEntityException
     */
    private function defineRedirectPath(Redirect $resultRedirect, Transaction $transaction): void
    {
        if ($transaction->isTransactionReserved() || $transaction->isTransactionCaptured()) {
            $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
        } else {
            $orderId = $this->getVippsQuote() ? $this->getVippsQuote()->getOrderId() : null;
            if (!$this->isCartPersistent() && $orderId) {
                $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
            } else {
                $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
            }
        }
    }
}
