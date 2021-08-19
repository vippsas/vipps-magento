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

use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\LocalizedExceptionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Gateway\Request\Initiate\InitiateBuilderInterface;
use Vipps\Payment\Model\Method\Vipps;

/**
 * Class InitExpress
 * @package Vipps\Payment\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class InitExpress implements ActionInterface
{
    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var CheckoutSession|SessionManagerInterface
     */
    private $checkoutSession;

    /**
     * @var CustomerSession|SessionManagerInterface
     */
    private $customerSession;

    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var LocalizedExceptionFactory
     */
    private $frameworkExceptionFactory;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var CheckoutHelper
     */
    private $checkoutHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * InitExpress constructor.
     *
     * @param ResultFactory $resultFactory
     * @param SessionManagerInterface $checkoutSession
     * @param SessionManagerInterface $customerSession
     * @param CommandManagerInterface $commandManager
     * @param CartRepositoryInterface $cartRepository
     * @param LocalizedExceptionFactory $frameworkExceptionFactory
     * @param ConfigInterface $config
     * @param ManagerInterface $messageManager
     * @param CheckoutHelper $checkoutHelper
     * @param LoggerInterface $logger
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ResultFactory $resultFactory,
        SessionManagerInterface $checkoutSession,
        SessionManagerInterface $customerSession,
        CommandManagerInterface $commandManager,
        CartRepositoryInterface $cartRepository,
        LocalizedExceptionFactory $frameworkExceptionFactory,
        ConfigInterface $config,
        ManagerInterface $messageManager,
        CheckoutHelper $checkoutHelper,
        LoggerInterface $logger
    ) {
        $this->resultFactory = $resultFactory;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->commandManager = $commandManager;
        $this->cartRepository = $cartRepository;
        $this->frameworkExceptionFactory = $frameworkExceptionFactory;
        $this->config = $config;
        $this->messageManager = $messageManager;
        $this->checkoutHelper = $checkoutHelper;
        $this->logger = $logger;
    }

    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            if (!$this->config->getValue('express_checkout')) {
                throw $this->frameworkExceptionFactory->create(
                    ['phrase' => __('Express Payment method is not available.')]
                );
            }

            $responseData = $this->initiatePayment();

            $this->checkoutSession->clearStorage();
            $resultRedirect->setPath($responseData['url'], ['_secure' => true]);

        } catch (LocalizedException $e) {
            $this->logger->critical($this->enlargeMessage($e));
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
        } catch (\Exception $e) {
            $this->logger->critical($this->enlargeMessage($e));
            $this->messageManager->addErrorMessage(
                __('An error occurred during request to Vipps. Please try again later.')
            );
            $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
        }

        return $resultRedirect;
    }

    /**
     * @return ResultInterface|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function initiatePayment()
    {
        $responseData = null;

        $quote = $this->checkoutSession->getQuote();

        $quote->getPayment()
            ->setAdditionalInformation(Vipps::METHOD_TYPE_KEY, Vipps::METHOD_TYPE_EXPRESS_CHECKOUT);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setShippingMethod(null);
        $quote->collectTotals();

        $responseData = $this->commandManager->initiatePayment(
            $quote->getPayment(),
            [
                'amount' => $quote->getGrandTotal(),
                InitiateBuilderInterface::PAYMENT_TYPE_KEY
                => InitiateBuilderInterface::PAYMENT_TYPE_EXPRESS_CHECKOUT
            ]
        );

        if (!$quote->getCheckoutMethod()) {
            if ($this->customerSession->isLoggedIn()) {
                $quote->setCheckoutMethod(Onepage::METHOD_CUSTOMER);
            } elseif ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }

        $quote->setIsActive(false);
        $this->cartRepository->save($quote);

        return $responseData;
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
