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
namespace Vipps\Payment\Controller\Payment\Klarna;

use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\{App\Action\Action,
    App\Action\Context,
    Controller\Result\Redirect,
    Controller\ResultFactory,
    Controller\ResultInterface,
    Exception\LocalizedException,
    App\ResponseInterface,
    Session\SessionManagerInterface};
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Gateway\Request\Initiate\InitiateBuilderInterface;
use Vipps\Payment\Model\Method\Vipps;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\CouldNotSaveException;

/**
 * Class InitRegular
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class InitRegular extends Action
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
     * InitRegular constructor.
     *
     * @param Context $context
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
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        CommandManagerInterface $commandManager,
        SessionManagerInterface $checkoutSession,
        SessionManagerInterface $customerSession,
        CheckoutHelper $checkoutHelper,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger,
        CartManagementInterface $cartManagement,
        GuestPaymentInformationManagementInterface $guestPaymentInformationManagement,
        PaymentInformationManagementInterface $paymentInformationManagement,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
    ) {
        parent::__construct($context);
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
            if (!$quote) {
                throw new LocalizedException(__('Could not initiate the payment. Please, reload the page.'));
            }

            // init Vipps payment and retrieve redirect url
            $responseData = $this->initiatePayment($quote);

            $this->placeOrder($quote);

            $this->checkoutSession->clearStorage();
            $response->setUrl($responseData['url']);
        } catch (LocalizedException $e) {
            $this->logger->critical($e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
            $response->setPath('checkout/cart');
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
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
     * @return \Magento\Payment\Gateway\Command\ResultInterface|null
     */
    private function initiatePayment(CartInterface $quote)
    {
        return $this->commandManager->initiatePayment(
            $quote->getPayment(),
            [
                'amount' => $quote->getGrandTotal(),
                InitiateBuilderInterface::PAYMENT_TYPE_KEY => InitiateBuilderInterface::PAYMENT_TYPE_REGULAR_PAYMENT
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
        $quote->getPayment()
            ->setAdditionalInformation(Vipps::METHOD_TYPE_KEY, Vipps::METHOD_TYPE_REGULAR_CHECKOUT);

        $this->setCheckoutMethod($quote);

        $maskedQuoteId = $this->quoteIdToMaskedQuoteId->execute($quote->getId());
        switch ($quote->getCheckoutMethod()) {
            case Onepage::METHOD_CUSTOMER:
                $this->paymentInformationManagement->savePaymentInformationAndPlaceOrder(
                    $maskedQuoteId,
                    $quote->getPayment()
                );
                break;
            case Onepage::METHOD_GUEST:
                $this->guestPaymentInformationManagement->savePaymentInformationAndPlaceOrder(
                    $maskedQuoteId,
                    $quote->getCustomerEmail(),
                    $quote->getPayment()
                );
                break;
            case Onepage::METHOD_REGISTER:
            default:
                break;
        }
    }

    /**
     * @param Quote $quote
     */
    private function setCheckoutMethod(Quote $quote)
    {
        if (!$quote->getCheckoutMethod()) {
            if ($this->customerSession->isLoggedIn()) {
                $quote->setCheckoutMethod(Onepage::METHOD_CUSTOMER);
            } elseif ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }
    }
}
