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
    Exception\InputException,
    Exception\LocalizedException,
    Exception\NoSuchEntityException,
    Session\SessionManagerInterface};
use Magento\Quote\{Api\CartRepositoryInterface, Api\Data\CartInterface, Model\Quote};
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\{Api\CommandManagerInterface,
    Api\Data\QuoteInterface,
    Api\QuoteRepositoryInterface,
    Gateway\Transaction\TransactionBuilder,
    Model\Gdpr\Compliance,
    Model\OrderLocator,
    Model\OrderPlace,
    Model\Quote\AttemptManagement,
    Model\QuoteManagement};
use Zend\Http\Response as ZendResponse;

/**
 * Class Fallback
 * @package Vipps\Payment\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Fallback extends Action implements CsrfAwareActionInterface
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var SessionManagerInterface|Session
     */
    private $checkoutSession;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * @var OrderPlace
     */
    private $orderPlace;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var OrderInterface
     */
    private $order;

    /**
     * @var CartInterface
     */
    private $quote;

    /**
     * @var QuoteRepositoryInterface
     */
    private $vippsQuoteRepository;

    /**
     * @var OrderLocator
     */
    private $orderLocator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Compliance
     */
    private $gdprCompliance;

    /**
     * @var QuoteManagement
     */
    private $vippsQuoteManagement;

    /**
     * @var AttemptManagement
     */
    private $attemptManagement;

    /**
     * @var QuoteInterface
     */
    private $vippsQuote;

    /**
     * Fallback constructor.
     *
     * @param Context $context
     * @param CommandManagerInterface $commandManager
     * @param SessionManagerInterface $checkoutSession
     * @param TransactionBuilder $transactionBuilder
     * @param OrderPlace $orderPlace
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteRepositoryInterface $vippsQuoteRepository
     * @param QuoteManagement $vippsQuoteManagement
     * @param AttemptManagement $attemptManagement
     * @param OrderLocator $orderLocator
     * @param Compliance $compliance
     * @param LoggerInterface $logger
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        CommandManagerInterface $commandManager,
        SessionManagerInterface $checkoutSession,
        TransactionBuilder $transactionBuilder,
        OrderPlace $orderPlace,
        CartRepositoryInterface $cartRepository,
        QuoteRepositoryInterface $vippsQuoteRepository,
        QuoteManagement $vippsQuoteManagement,
        AttemptManagement $attemptManagement,
        OrderLocator $orderLocator,
        Compliance $compliance,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->commandManager = $commandManager;
        $this->checkoutSession = $checkoutSession;
        $this->transactionBuilder = $transactionBuilder;
        $this->orderPlace = $orderPlace;
        $this->cartRepository = $cartRepository;
        $this->vippsQuoteRepository = $vippsQuoteRepository;
        $this->orderLocator = $orderLocator;
        $this->logger = $logger;
        $this->gdprCompliance = $compliance;
        $this->vippsQuoteManagement = $vippsQuoteManagement;
        $this->attemptManagement = $attemptManagement;
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

            $quote = $this->getQuote();
            $order = $this->placeOrder();

            $this->updateCheckoutSession($quote, $order);
            /** @var ZendResponse $result */
            $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
        } catch (LocalizedException $e) {
            $this->logger->critical($e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $this->messageManager->addErrorMessage(__('An error occurred during order place.'));
            $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
        } finally {
            $compliant = $this->gdprCompliance->process($this->getRequest()->getRequestString());
            $this->logger->debug($compliant);
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
     * @return QuoteInterface
     * @throws NoSuchEntityException
     */
    private function getVippsQuote()
    {
        if (null === $this->vippsQuote) {
            $this->vippsQuote = $this->vippsQuoteRepository->loadByOrderId($this->getRequest()->getParam('order_id'));
        }
        return $this->vippsQuote;
    }

    /**
     * Retrieve quote from quote repository if no then from order
     *
     * @return CartInterface|bool
     * @throws NoSuchEntityException
     */
    private function getQuote()
    {
        if (null === $this->quote) {
            $vippsQuote = $this->getVippsQuote();
            $this->quote = $this->cartRepository->get($vippsQuote->getQuoteId());
        }
        return $this->quote;
    }

    /**
     * @return OrderInterface|null
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Vipps\Payment\Gateway\Exception\VippsException
     * @throws \Vipps\Payment\Gateway\Exception\WrongAmountException
     */
    private function placeOrder()
    {
        $quote = $this->getQuote();
        $transaction = $this->getPaymentDetails();

        if ($transaction->isTransactionCancelled()) {
            $this->restoreQuote($quote);
            throw new LocalizedException(__('Your order was canceled in Vipps.'));
        }

        if ($transaction->isTransactionReserved()) {
            $order = $this->orderPlace->execute($quote, $transaction);
            if (!$order) {
                throw new LocalizedException(__('An error occurred during order place.'));
            }
            return $order;
        }

        $this->restoreQuote($quote);
        throw new LocalizedException(__('The order was not reserved in Vipps.'));
    }

    /**
     * @return \Vipps\Payment\Gateway\Transaction\Transaction
     * @throws \Vipps\Payment\Gateway\Exception\VippsException
     */
    private function getPaymentDetails()
    {
        $response = $this->commandManager->getPaymentDetails(
            [
                'orderId' => $this->getRequest()->getParam('order_id')
            ]
        );

        return $this->transactionBuilder->setData($response)->build();
    }

    /**
     * @param $quote
     */
    private function restoreQuote($quote)
    {
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
     * @param CartInterface $quote
     * @param OrderInterface $order
     */
    private function updateCheckoutSession(CartInterface $quote, OrderInterface $order = null)
    {
        $this->checkoutSession->setLastQuoteId($quote->getId());
        if ($order) {
            $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
            $this->checkoutSession->setLastOrderId($order->getEntityId());
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
            $this->checkoutSession->setLastOrderStatus($order->getStatus());
        }
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
}
