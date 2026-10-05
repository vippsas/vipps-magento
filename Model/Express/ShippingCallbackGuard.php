<?php
/**
 * Copyright 2026 Vipps
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

namespace Vipps\Payment\Model\Express;

use Laminas\Http\Response;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\Model\CallbackAuthorization;

/**
 * Authenticates the Express shipping details callback.
 *
 * The callback is public and identifies the cart by its reserved order id, which is sequential and so not a
 * secret. The only proof that a request comes from Vipps/MobilePay is the Authorization header carrying the
 * callbackAuthorizationToken we sent when initiating the payment.
 */
class ShippingCallbackGuard
{
    /**
     * @var QuoteRepositoryInterface
     */
    private $vippsQuoteRepository;

    /**
     * @var CallbackAuthorization
     */
    private $callbackAuthorization;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param QuoteRepositoryInterface $vippsQuoteRepository
     * @param CallbackAuthorization $callbackAuthorization
     * @param Json $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        QuoteRepositoryInterface $vippsQuoteRepository,
        CallbackAuthorization $callbackAuthorization,
        Json $serializer,
        LoggerInterface $logger
    ) {
        $this->vippsQuoteRepository = $vippsQuoteRepository;
        $this->callbackAuthorization = $callbackAuthorization;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Return the HTTP status to reject the request with, or null when it may proceed.
     *
     * An unknown reference is rejected exactly like a wrong token, so the endpoint does not reveal which
     * references exist.
     *
     * @param RequestInterface $request
     *
     * @return int|null
     */
    public function getRejectionStatus(RequestInterface $request): ?int
    {
        try {
            $body = $this->serializer->unserialize((string)$request->getContent());
        } catch (\InvalidArgumentException $e) {
            return Response::STATUS_CODE_400;
        }

        $reference = is_array($body) ? ($body['reference'] ?? null) : null;
        if (!is_string($reference) || $reference === '') {
            return Response::STATUS_CODE_400;
        }

        if (!$this->isAuthorized($reference, $request->getHeader('Authorization'))) {
            // error, not warning: the Vipps logger drops anything below error unless debug logging is on.
            $this->logger->error(
                sprintf('Rejected unauthorised shipping details callback for reference %s', $reference)
            );
            return Response::STATUS_CODE_403;
        }

        return null;
    }

    /**
     * @param string $reference
     * @param string|false $presentedToken
     *
     * @return bool
     */
    private function isAuthorized(string $reference, $presentedToken): bool
    {
        try {
            $vippsQuote = $this->vippsQuoteRepository->loadByOrderId($reference);
        } catch (NoSuchEntityException $e) {
            return false;
        }

        return $this->callbackAuthorization->isAuthorized(
            $vippsQuote->getAuthToken(),
            is_string($presentedToken) ? $presentedToken : null
        );
    }
}
