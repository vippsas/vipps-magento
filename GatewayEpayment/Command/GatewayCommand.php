<?php
/**
 * Copyright 2023 Vipps
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
namespace Vipps\Payment\GatewayEpayment\Command;

use Laminas\Http\Response;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Json\DecoderInterface;
use Magento\Framework\Phrase;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Model\Profiling\ProfilerInterface;

/**
 * Class GatewayCommand
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GatewayCommand implements CommandInterface
{
    /**
     * @var BuilderInterface
     */
    private $requestBuilder;

    /**
     * @var TransferFactoryInterface
     */
    private $transferFactory;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var HandlerInterface
     */
    private $handler;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DecoderInterface
     */
    private $jsonDecoder;

    /**
     * @var ProfilerInterface
     */
    private $profiler;

    /**
     * GatewayCommand constructor.
     *
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param LoggerInterface $logger
     * @param DecoderInterface $jsonDecoder
     * @param ProfilerInterface $profiler
     * @param HandlerInterface|null $handler
     * @param ValidatorInterface|null $validator
     */
    public function __construct(
        BuilderInterface $requestBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $client,
        LoggerInterface $logger,
        DecoderInterface $jsonDecoder,
        ?ProfilerInterface $profiler = null,
        ?HandlerInterface $handler = null,
        ?ValidatorInterface $validator = null
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->transferFactory = $transferFactory;
        $this->client = $client;
        $this->logger = $logger;
        $this->jsonDecoder = $jsonDecoder;
        $this->profiler = $profiler;
        $this->handler = $handler;
        $this->validator = $validator;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $commandSubject
     *
     * @return ResultInterface|array|null
     * @throws ClientException
     * @throws ConverterException
     * @throws LocalizedException
     */
    public function execute(array $commandSubject)
    {
        $transfer = $this->transferFactory->create(
            $this->requestBuilder->build($commandSubject)
        );

        $commandSubject['transferObject'] = $transfer;

        $result = $this->client->placeRequest($transfer);

        /** @var Response $response */
        $response = $result['response'];
        try {
            $responseBody = $this->jsonDecoder->decode($response->getContent());
        } catch (\Exception $e) {
            $responseBody = [];
        }

        if ($this->profiler !== null) {
            $this->profiler->save($transfer, $response);
        }

        if (!$response->isSuccess()) {
            $error = $this->extractError($responseBody);
            $errorCode = $error['code'] ?? $response->getStatusCode();
            $errorMessage = $error['message'] ?? $response->getReasonPhrase();
            $message = sprintf(
                'Request error. Code: "%s", message: "%s"',
                $errorCode,
                $errorMessage
            );
            $this->logger->critical($message);
            throw new \Exception($errorMessage, $errorCode);
        }

        /** Validating Success response body by specific command validators */
        if ($this->validator !== null) {
            $validationResult = $this->validator->validate(
                array_merge($commandSubject, ['jsonData' => $responseBody])
            );
            if (!$validationResult->isValid()) {
                $this->logValidationFails($validationResult->getFailsDescription());
                throw new CommandException(
                    __('Transaction validation failed.')
                );
            }
        }

        /** Handling response after validation is success */
        if ($this->handler) {
            $this->handler->handle($commandSubject, $responseBody);
        }

        return $responseBody;
    }

    /**
     * @param Phrase[] $fails
     *
     * @return void
     */
    private function logValidationFails(array $fails)
    {
        foreach ($fails as $failPhrase) {
            $this->logger->critical((string) $failPhrase);
        }
    }

    /**
     * Method to extract error code and message from response.
     *
     * @param $responseBody
     *
     * @return array
     */
    private function extractError($responseBody)
    {
        return [
            'code' => isset($responseBody[0]['errorCode']) ? $responseBody[0]['errorCode'] : null,
            'message' => isset($responseBody[0]['errorMessage']) ? $responseBody[0]['errorMessage'] : null,
        ];
    }
}
