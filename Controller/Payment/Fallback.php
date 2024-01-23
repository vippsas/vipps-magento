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

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\Model\Fallback\AuthoriseProxy;
use Vipps\Payment\Model\Gdpr\Compliance;
use Vipps\Payment\Model\OrderLocator;
use Vipps\Payment\Model\Transaction\StatusVisitor;
use Vipps\Payment\Model\TransactionProcessor;

/**
 * Class Fallback
 * @package Vipps\Payment\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Fallback implements ActionInterface, CsrfAwareActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

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
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var QuoteInterface
     */
    private $vippsQuote;

    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var OrderLocator
     */
    private $orderLocator;

    /**
     * @var OrderInterface|null
     */
    private $order;
    private StatusVisitor $statusVisitor;
    private AuthoriseProxy $authoriseProxy;

    /**
     * Fallback constructor.
     *
     * @param ResultFactory $resultFactory
     * @param RequestInterface $request
     * @param SessionManagerInterface $checkoutSession
     * @param TransactionProcessor $transactionProcessor
     * @param CartRepositoryInterface $cartRepository
     * @param ManagerInterface $messageManager
     * @param QuoteRepositoryInterface $vippsQuoteRepository
     * @param OrderManagementInterface $orderManagement
     * @param OrderLocator $orderLocator
     * @param Compliance $compliance
     * @param LoggerInterface $logger
     * @param ConfigInterface $config
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ResultFactory            $resultFactory,
        RequestInterface         $request,
        SessionManagerInterface  $checkoutSession,
        TransactionProcessor     $transactionProcessor,
        CartRepositoryInterface  $cartRepository,
        ManagerInterface         $messageManager,
        QuoteRepositoryInterface $vippsQuoteRepository,
        OrderManagementInterface $orderManagement,
        OrderLocator             $orderLocator,
        Compliance               $compliance,
        LoggerInterface          $logger,
        ConfigInterface          $config,
        StatusVisitor            $statusVisitor,
        AuthoriseProxy           $authoriseProxy
    ) {
        $this->resultFactory = $resultFactory;
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->transactionProcessor = $transactionProcessor;
        $this->cartRepository = $cartRepository;
        $this->messageManager = $messageManager;
        $this->vippsQuoteRepository = $vippsQuoteRepository;
        $this->orderLocator = $orderLocator;
        $this->gdprCompliance = $compliance;
        $this->orderManagement = $orderManagement;
        $this->logger = $logger;
        $this->config = $config;
        $this->statusVisitor = $statusVisitor;
        $this->authoriseProxy = $authoriseProxy;
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        $transaction = null;
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $vippsQuote = $this->getVippsQuote();

            $this->authoriseProxy->do($this->request, $vippsQuote);

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

            $quoteCouldBeRestored = $transaction
                && ($this->statusVisitor->isCanceled($transaction) || $this->statusVisitor->isExpired($transaction));
            $order = $this->getOrder();

            if ($quoteCouldBeRestored && $cartPersistence) {
                $this->restoreQuote($vippsQuote);
            } elseif ($order) {
                $this->storeLastOrder($order);
            }

            if (isset($e)) {
                if (!$cartPersistence && $order) {
                    $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
                } else {
                    $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
                }
            }
            $this->logger->debug($this->request->getRequestString());
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
        $quoteId = $this->checkoutSession->getQuoteId();
        $trace = $e->getTraceAsString();
        $message = $e->getMessage();

        return "QuoteID: $quoteId. Exception message: $message. Stack Trace $trace";
    }

    /**
     * Request authorization process
     *
     * @throws LocalizedException
     */
    private function authorize()
    {
        if (!$this->request->getParam('order_id') ||
            !$this->request->getParam('auth_token')
        ) {
            throw new LocalizedException(__('Invalid request parameters'));
        }

        $vippsQuote = $this->getVippsQuote();
        if ($vippsQuote->getAuthToken() !== $this->request->getParam('auth_token', '')) {
            throw new LocalizedException(__('Invalid request'));
        }
    }

    /**
     * @param bool $forceReload
     *
     * @return QuoteInterface
     * @throws NoSuchEntityException
     */
    private function getVippsQuote($forceReload = false): QuoteInterface
    {
        if (null === $this->vippsQuote || $forceReload) {
            $this->vippsQuote = $this->vippsQuoteRepository
                ->loadByOrderId($this->authoriseProxy->getOrderId($this->request));
        }

        return $this->vippsQuote;
    }

    /**
     * @param Redirect $resultRedirect
     * @param Transaction|Payment $transaction
     *
     * @return Redirect
     * @throws \Exception
     */
    private function prepareResponse(Redirect $resultRedirect, $transaction)
    {
        $this->defineMessage($transaction);
        $this->defineRedirectPath($resultRedirect, $transaction);

        return $resultRedirect;
    }

    /**
     * Method store order info to checkout session
     */
    private function storeLastOrder(OrderInterface $order)
    {
        $this->checkoutSession->clearStorage();
        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastOrderId($order->getEntityId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderStatus($order->getStatus());
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
     * @return mixed
     */
    private function isCartPersistent()
    {
        return $this->config->getValue('cancellation_cart_persistence');
    }

    /**
     * @param Transaction|Payment $transaction
     * @return void
     */
    private function defineMessage($transaction): void
    {
        if ($this->statusVisitor->isCanceled($transaction)) {
            $this->messageManager->addWarningMessage(__('Your order was cancelled in Vipps.'));
        } elseif (
            $this->statusVisitor->isReserved($transaction)
            || $this->statusVisitor->isCaptured($transaction)) {
            $this->messageManager->addWarningMessage(__('Your order was successfully placed.'));
        } elseif ($this->statusVisitor->isExpired($transaction)) {
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
     * @param Transaction|Payment $transaction
     *
     * @throws NoSuchEntityException
     */
    private function defineRedirectPath(Redirect $resultRedirect, $transaction): void
    {
        if ($this->statusVisitor->isReserved($transaction)
            || $this->statusVisitor->isCaptured($transaction)
        ) {
            $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
        } else {
            $orderId = $this->getOrder() ? $this->getOrder()->getEntityId() : null;
            if (!$this->isCartPersistent() && $orderId) {
                $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
            } else {
                $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
            }
        }
    }

    /**
     * @param false $forceReload
     *
     * @return OrderInterface|null
     * @throws NoSuchEntityException
     */
    private function getOrder($forceReload = false)
    {
        if (null === $this->order || $forceReload) {
            $vippsQuote = $this->getVippsQuote($forceReload);
            $this->order = $this->orderLocator->get($vippsQuote->getReservedOrderId());
        }

        return $this->order;
    }
}
