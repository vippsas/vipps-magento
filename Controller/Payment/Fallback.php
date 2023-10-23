<?php
/**
 * Copyright 2022 Vipps
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

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\GatewayEcomm\Data\Payment;
use Vipps\Payment\Model\OrderLocator;
use Vipps\Payment\Model\Quote;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\GatewayEcomm\Data\Session;
use Vipps\Payment\GatewayEcomm\Model\TransactionProcessor as SessionProcessor;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\ConfigInterface;

class Fallback implements ActionInterface
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;
    /**
     * @var QuoteRepositoryInterface
     */
    private $quoteRepository;
    /**
     * @var SessionProcessor
     */
    private $sessionProcessor;
    /**
     * @var OrderLocator
     */
    private $orderLocator;
    /**
     * @var OrderInterface
     */
    private $order;
    /**
     * @var ManagerInterface
     */
    private $messageManager;
    /**
     * @var ConfigInterface
     */
    private $config;
    /**
     * @var QuoteInterface
     */
    private $vippsQuote;
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var ResultFactory
     */
    private $resultFactory;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Fallback constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteRepositoryInterface $quoteRepository
     * @param SessionProcessor $sessionProcessor
     * @param OrderLocator $orderLocator
     * @param ManagerInterface $messageManager
     * @param ConfigInterface $config
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        QuoteRepositoryInterface $quoteRepository,
        SessionProcessor $sessionProcessor,
        OrderLocator $orderLocator,
        ManagerInterface $messageManager,
        ConfigInterface $config,
        RequestInterface $request,
        ResultFactory $resultFactory,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->quoteRepository = $quoteRepository;
        $this->sessionProcessor = $sessionProcessor;
        $this->orderLocator = $orderLocator;
        $this->messageManager = $messageManager;
        $this->config = $config;
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        $session = null;

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $this->authorize();

            $vippsQuote = $this->getVippsQuote();
            $session = $this->sessionProcessor->process($vippsQuote);

            $this->defineMessage($session);
        } catch (LocalizedException $e) {
            $this->logger->critical($this->wrapExceptionMessage($e));
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($this->wrapExceptionMessage($e));
            $this->messageManager->addErrorMessage(
                __('A server error stopped your transaction from being processed.'
                    . ' Please contact to store administrator.')
            );
        } finally {
            $resultRedirect = $this->prepareResponse($resultRedirect, $session);
            $this->logger->debug($this->request->getRequestString());
        }

        return $resultRedirect;
    }

    private function authorize()
    {
        if (!$this->request->getParam('reference')) {
            throw new LocalizedException(__('Invalid request parameters'));
        }

        $vippsQuote = $this->getVippsQuote();
//        if ($vippsQuote->getStatus() !== Quote::STATUS_NEW) {
//            throw new LocalizedException(__('Invalid request'));
//        }
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
            $this->vippsQuote = $this->quoteRepository
                ->loadByOrderId($this->request->getParam('reference'));
        }

        return $this->vippsQuote;
    }

    /**
     * @param Redirect $resultRedirect
     * @param Session $payment
     *
     * @return Redirect
     * @throws \Exception
     */
    private function prepareResponse(Redirect $resultRedirect, Payment $payment = null)
    {
        $cartPersistent = $this->config->getValue('cancellation_cart_persistence');

        if ($payment && $payment->isTerminated() && $cartPersistent) {
            $this->restoreQuote($this->getVippsQuote(true));
            $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
        } else {
            $this->storeLastOrder();
            if ($payment && $payment->isAuthorised()) {
                $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
            } else {
                $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
            }
        }

        return $resultRedirect;
    }

    /**
     * Method store order info to checkout session
     */
    private function storeLastOrder()
    {
        $order = $this->getOrder();
        if (!$order) {
            return;
        }

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

    private function defineMessage(\Vipps\Payment\GatewayEcomm\Data\Payment $payment): void
    {
        if ($payment->isTerminated()) {
            $this->messageManager->addWarningMessage(__('Your order was cancelled in Vipps.'));
        } elseif ($payment->isAuthorised()) {
            $this->messageManager->addSuccessMessage(__('Your order was successfully placed.'));
        } else {
            $this->messageManager->addWarningMessage(
                __('We have not received a confirmation that order was reserved. It will be checked later again.')
            );
        }
    }

    /**
     * @param false $forceReload
     *
     * @return OrderInterface|null
     * @throws NoSuchEntityException
     */
    private function getOrder($forceReload = false): ?OrderInterface
    {
        if (null === $this->order || $forceReload) {
            $vippsQuote = $this->getVippsQuote($forceReload);
            $this->order = $this->orderLocator->get($vippsQuote->getReservedOrderId());
        }

        return $this->order;
    }

    private function wrapExceptionMessage($e): string
    {
        $message =
            \sprintf('QuoteID: %s. Exception message: %s.', $this->checkoutSession->getQuoteId(), $e->getMessage());

        if ($this->config->getValue('debug')) {
            $message .= \sprintf('Stack Trace %s', $e->getTraceAsString());
        }

        return $message;
    }
}
