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

namespace Vipps\Payment\Controller\Payment\Klarna;

use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Gateway\Command\ResultInterface as PaymentResultInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Payment\CommandManagerInterface;
use Vipps\Payment\Gateway\Request\Initiate\InitiateBuilderInterface;
use Vipps\Payment\Model\Method\Vipps;
use function __;

/**
 * Class InitRegular
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class InitRegular implements ActionInterface
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var CheckoutSession|SessionManagerInterface
     */
    private $checkoutSession;

    /**
     * @var CustomerSession|SessionManagerInterface
     */
    private $customerSession;

    /**
     * @var CheckoutHelper
     */
    private $checkoutHelper;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var GuestPaymentInformationManagementInterface
     */
    private $guestPaymentInformationManagement;

    /**
     * @var PaymentInformationManagementInterface
     */
    private $paymentInformationManagement;

    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;

    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * InitRegular constructor.
     *
     * @param ResultFactory $resultFactory
     * @param CommandManagerInterface $commandManager
     * @param SessionManagerInterface $checkoutSession
     * @param SessionManagerInterface $customerSession
     * @param CheckoutHelper $checkoutHelper
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     * @param CartManagementInterface $cartManagement
     * @param GuestPaymentInformationManagementInterface $guestPaymentInformationManagement
     * @param PaymentInformationManagementInterface $paymentInformationManagement
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param ManagerInterface $messageManager
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ResultFactory $resultFactory,
        CommandManagerInterface $commandManager,
        SessionManagerInterface $checkoutSession,
        SessionManagerInterface $customerSession,
        CheckoutHelper $checkoutHelper,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger,
        CartManagementInterface $cartManagement,
        GuestPaymentInformationManagementInterface $guestPaymentInformationManagement,
        PaymentInformationManagementInterface $paymentInformationManagement,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        ManagerInterface $messageManager
    ) {
        $this->resultFactory = $resultFactory;
        $this->commandManager = $commandManager;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->checkoutHelper = $checkoutHelper;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
        $this->cartManagement = $cartManagement;
        $this->guestPaymentInformationManagement = $guestPaymentInformationManagement;
        $this->paymentInformationManagement = $paymentInformationManagement;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->messageManager = $messageManager;
    }

    /**
     * {@inheritdoc}
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        /** @var Redirect $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $quote = $this->checkoutSession->getQuote();

            $responseData = $this->initiatePayment($quote);
            $this->placeOrder($quote);

            $this->checkoutSession->clearStorage();
            $response->setUrl($responseData['url']);
        } catch (LocalizedException $e) {
            $this->logger->critical($this->enlargeMessage($e));
            $this->messageManager->addErrorMessage($e->getMessage());
            $response->setPath('checkout/cart');
        } catch (\Exception $e) {
            $this->logger->critical($this->enlargeMessage($e));
            $this->messageManager->addErrorMessage(
                __('An error occurred during request to Vipps. Please try again later.')
            );
            $response->setPath('checkout/cart');
        }

        return $response;
    }

    /**
     * Initiate payment on Vipps side
     *
     * @param CartInterface|Quote $quote
     *
     * @return PaymentResultInterface|null
     */
    private function initiatePayment(CartInterface $quote)
    {
        return $this->commandManager->initiatePayment(
            $quote->getPayment(),
            [
                'amount' => $quote->getGrandTotal(),
                InitiateBuilderInterface::PAYMENT_TYPE_KEY => InitiateBuilderInterface::PAYMENT_TYPE_REGULAR_PAYMENT,
                Vipps::METHOD_TYPE_KEY => Vipps::METHOD_TYPE_REGULAR_CHECKOUT
            ]
        );
    }

    /**
     * @param CartInterface|Quote $quote
     *
     * @throws CouldNotSaveException
     * @throws \Exception
     */
    private function placeOrder(CartInterface $quote): void
    {
        $this->setCheckoutMethod($quote);

        $maskedQuoteId = $this->quoteIdToMaskedQuoteId->execute((int)$quote->getId());
        switch ($quote->getCheckoutMethod(true)) {
            case Onepage::METHOD_CUSTOMER:
                $this->paymentInformationManagement->savePaymentInformationAndPlaceOrder(
                    $quote->getId(),
                    $quote->getPayment()
                );
                break;
            default:
                $this->guestPaymentInformationManagement->savePaymentInformationAndPlaceOrder(
                    $maskedQuoteId,
                    $quote->getCustomerEmail(),
                    $quote->getPayment()
                );
                break;
        }
    }

    /**
     * @param Quote $quote
     */
    private function setCheckoutMethod(Quote $quote)
    {
        if (!$quote->getCheckoutMethod(true)) {
            if ($this->customerSession->isLoggedIn()) {
                $quote->setCheckoutMethod(Onepage::METHOD_CUSTOMER);
            } elseif ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }
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
