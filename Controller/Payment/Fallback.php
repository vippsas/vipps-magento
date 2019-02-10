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
    Api\Data\QuoteStatusInterface,
    Gateway\Exception\MerchantException,
    Gateway\Request\Initiate\MerchantDataBuilder,
    Gateway\Transaction\TransactionBuilder,
    Model\Gdpr\Compliance,
    Model\OrderLocator,
    Model\OrderPlace,
    Model\Quote as VippsQuote,
    Model\Quote\Attempt,
    Model\Quote\AttemptManagement,
    Model\QuoteLocator,
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
     * @var QuoteLocator
     */
    private $quoteLocator;

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
     * Fallback constructor.
     *
     * @param Context $context
     * @param CommandManagerInterface $commandManager
     * @param SessionManagerInterface $checkoutSession
     * @param TransactionBuilder $transactionBuilder
     * @param OrderPlace $orderPlace
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteLocator $quoteLocator
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
        QuoteLocator $quoteLocator,
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
        $this->quoteLocator = $quoteLocator;
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
            $order = $this->getOrder();
            $vippsQuote = $this->vippsQuoteManagement->getByQuote($quote);
            $vippsQuote->setStatus(QuoteStatusInterface::STATUS_PROCESSING);
            $attempt = $this->attemptManagement->createAttempt($vippsQuote);
            if (!$order) {
                $order = $this->placeOrder($quote, $vippsQuote, $attempt);
            }
            $attemptMessage = __('Placed');
            $vippsQuote->setStatus(QuoteStatusInterface::STATUS_PLACED);
            $this->updateCheckoutSession($quote, $order);
            /** @var ZendResponse $result */
            $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
        } catch (LocalizedException $e) {
            $this->logger->critical($e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
            $attemptMessage = $e->getMessage();
            $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
        } catch (\Exception $e) {
            $attemptMessage = $e->getMessage();
            $this->logger->critical($e->getMessage());
            $this->messageManager->addErrorMessage(__('An error occurred during payment status update.'));
            $resultRedirect->setPath('checkout/onepage/failure', ['_secure' => true]);
        } finally {
            $compliant = $this->gdprCompliance->process($this->getRequest()->getRequestString());
            $this->logger->debug($compliant);

            if (isset($attempt)) {
                $attempt->setMessage($attemptMessage);
                $this->attemptManagement->save($attempt);
                $this->vippsQuoteManagement->save($vippsQuote);
            }
        }
        return $resultRedirect;
    }

    /**
     * Request authorization process
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function authorize()
    {
        if (!$this->getRequest()->getParam('order_id')
            || !$this->getRequest()->getParam('access_token')
        ) {
            throw new LocalizedException(__('Invalid request parameters'));
        }

        /** @var Quote $quote */
        $quote = $this->getQuote();
        if ($quote) {
            $additionalInfo = $quote->getPayment()->getAdditionalInformation();

            $fallbackAuthToken = $additionalInfo[MerchantDataBuilder::FALLBACK_AUTH_TOKEN] ?? null;
            $accessToken = $this->getRequest()->getParam('access_token', '');
            if ($fallbackAuthToken === $accessToken) {
                return true;
            }
        }

        throw new LocalizedException(__('Invalid request'));
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
            $this->quote = $this->quoteLocator->get($this->getRequest()->getParam('order_id')) ?: false;
            if ($this->quote) {
                return $this->quote;
            }
            $order = $this->getOrder();
            if ($order) {
                $this->quote = $this->cartRepository->get($order->getQuoteId());
            } else {
                $this->quote = false;
            }
        }
        return $this->quote;
    }

    /**
     * Retrieve order object from repository based on increment id
     *
     * @return bool|OrderInterface
     */
    private function getOrder()
    {
        if (null === $this->order) {
            $this->order = $this->orderLocator->get($this->getRequest()->getParam('order_id')) ?: false;
        }
        return $this->order;
    }

    /**
     * @param CartInterface $quote
     *
     * @param VippsQuote $vippsQuote
     * @param Attempt $attempt
     * @return OrderInterface|null
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws MerchantException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Vipps\Payment\Gateway\Exception\VippsException
     * @throws \Vipps\Payment\Gateway\Exception\WrongAmountException
     */
    private function placeOrder(CartInterface $quote, VippsQuote $vippsQuote, Attempt $attempt)
    {
        try {
            $response = $this->commandManager->getOrderStatus(
                $this->getRequest()->getParam('order_id')
            );
            $transaction = $this->transactionBuilder->setData($response)->build();
            if ($transaction->isTransactionAborted()) {
                $attempt->setMessage('Transaction was cancelled in Vipps');
                $vippsQuote->setStatus(QuoteStatusInterface::STATUS_CANCELED);
                $this->restoreQuote();
            }
            $order = $this->orderPlace->execute($quote, $transaction);
            if (!$order) {
                throw new LocalizedException(
                    __('Couldn\'t get information about order status right now. Please contact a store administrator.')
                );
            }
            return $order;
        } catch (MerchantException $e) {
            //@todo workaround for vipps issue with order cancellation (delete this condition after fix) //@codingStandardsIgnoreLine
            if ($e->getCode() == MerchantException::ERROR_CODE_REQUESTED_ORDER_NOT_FOUND) {
                $this->restoreQuote();
            } else {
                throw $e;
            }
        }
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function restoreQuote()
    {
        $quote = $this->getQuote();

        /** @var Quote $quote */
        $quote->setIsActive(true);
        $quote->setReservedOrderId(null);
        $this->cartRepository->save($quote);

        $this->checkoutSession->setLastQuoteId($quote->getId());
        $this->checkoutSession->replaceQuote($quote);
        throw new LocalizedException(__('Your order was canceled in Vipps.'));
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
