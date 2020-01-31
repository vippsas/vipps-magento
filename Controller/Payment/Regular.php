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

use Magento\Framework\{
    Controller\ResultFactory, Controller\ResultInterface, App\Action\Context, App\Action\Action,
    Exception\LocalizedException,App\ResponseInterface,Session\SessionManagerInterface
};
use Vipps\Payment\{
    Api\CommandManagerInterface,
    Gateway\Exception\VippsException,
    Gateway\Request\Initiate\InitiateBuilderInterface
};
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
     * @var \Magento\Checkout\Model\Session
     */
    private $session;

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
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CommandManagerInterface $commandManager,
        SessionManagerInterface $session,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->commandManager = $commandManager;
        $this->session = $session;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $responseData = $this->initiatePayment();
            $this->session->clearStorage();
            return $this->prepareResponse($response, $responseData);
        } catch (VippsException $e) {
            $this->logger->critical($e->getMessage());
            $response->setData([
                'errorMessage' => $e->getMessage()
            ]);
        } catch (LocalizedException $e) {
            $response->setData([
                'errorMessage' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $response->setData([
                'errorMessage' => __('An error occurred during request to Vipps. Please try again later.' . $e->getMessage())
            ]);
        }

        return $response;
    }

    /**
     * Prepare response object
     *
     * @param ResponseInterface|ResultInterface $response
     * @param $responseData
     *
     * @return mixed
     */
    protected function prepareResponse($response, $responseData)
    {
        $response->setData($responseData);
        return $response;
    }

    /**
     * Initiate payment on Vipps side
     *
     * @return \Magento\Payment\Gateway\Command\ResultInterface|null
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function initiatePayment()
    {
        $quote = $this->session->getQuote();
        $responseData = $this->commandManager->initiatePayment(
            $quote->getPayment(),
            [
                'amount' => $quote->getGrandTotal(),
                InitiateBuilderInterface::PAYMENT_TYPE_KEY
                => InitiateBuilderInterface::PAYMENT_TYPE_REGULAR_PAYMENT
            ]
        );
        return $responseData;
    }
}
