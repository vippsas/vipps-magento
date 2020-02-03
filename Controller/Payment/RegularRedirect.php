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

use Magento\Framework\{Controller\Result\Redirect,
    Controller\ResultFactory,
    Controller\ResultInterface,
    Exception\LocalizedException,
    App\ResponseInterface};

/**
 * Class RegularRedirect
 * @package Vipps\Payment\Controller\Payment
 */
class RegularRedirect extends Regular
{
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
            $responseData = $this->initiatePayment();
            $this->getSession()->clearStorage();

            $response->setUrl($responseData['url']);
        } catch (LocalizedException $e) {
            $this->getLogger()->critical($e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
            $response->setPath('checkout/cart');
        } catch (\Exception $e) {
            $this->getLogger()->critical($e->getMessage());
            $this->messageManager
                ->addErrorMessage(__('An error occurred during request to Vipps. Please try again later.'));
            $response->setPath('checkout/cart');
        }

        return $response;
    }
}
