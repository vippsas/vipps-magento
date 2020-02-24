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

use Magento\Framework\{Controller\Result\Json,
    Controller\ResultFactory,
    Controller\ResultInterface,
    App\Action\Context,
    App\Action\Action,
    Exception\LocalizedException,
    Exception\NoSuchEntityException,
    App\ResponseInterface,
    Session\SessionManagerInterface};
use Magento\Quote\Api\CartRepositoryInterface;
use Vipps\Payment\{Api\CommandManagerInterface, Gateway\Request\Initiate\InitiateBuilderInterface, Model\Method\Vipps};
use Magento\Checkout\Model\Session;
use Psr\Log\LoggerInterface;

/**
 * Class Regular
 * @package Vipps\Payment\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Regular extends Action
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var SessionManagerInterface
     */
    private $session;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Regular constructor.
     *
     * @param Context $context
     * @param CommandManagerInterface $commandManager
     * @param SessionManagerInterface $session
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CommandManagerInterface $commandManager,
        SessionManagerInterface $session,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->commandManager = $commandManager;
        $this->session = $session;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        /** @var Json $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $responseData = $this->initiatePayment();
            $response->setData($responseData);
        } catch (LocalizedException $e) {
            $this->getLogger()->critical($e->getMessage());
            $response->setData(['message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->getLogger()->critical($e->getMessage());
            $response->setData([
                'message' => __('An error occurred during request to Vipps. Please try again later.')
            ]);
        }

        return $response;
    }

    /**
     * Initiate payment on Vipps side
     *
     * @return \Magento\Payment\Gateway\Command\ResultInterface|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function initiatePayment()
    {
        $quote = $this->getSession()->getQuote();
        if (!$quote) {
            throw new LocalizedException(__('Could not initiate payment. Please, reload the page.'));
        }

        $quote->getPayment()
            ->setAdditionalInformation(Vipps::METHOD_TYPE_KEY, Vipps::METHOD_TYPE_REGULAR_CHECKOUT);

        $responseData = $this->commandManager->initiatePayment(
            $quote->getPayment(),
            [
                'amount' => $quote->getGrandTotal(),
                InitiateBuilderInterface::PAYMENT_TYPE_KEY => InitiateBuilderInterface::PAYMENT_TYPE_REGULAR_PAYMENT
            ]
        );

        $this->cartRepository->save($quote);
        return $responseData;
    }

    /**
     * @return Session|SessionManagerInterface
     */
    protected function getSession()
    {
        return $this->session;
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }
}
