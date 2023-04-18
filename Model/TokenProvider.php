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
namespace Vipps\Payment\Model;

use Laminas\Http\Response;
use Magento\Framework\HTTP\Adapter\Curl as MagentoCurl;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Gateway\Exception\AuthenticationException;
use Vipps\Payment\Gateway\Http\Client\Curl;

/**
 * Class TokenProvider
 * @package Vipps\Payment\Model
 */
class TokenProvider implements TokenProviderInterface
{
    /**
     * Variable to reserve time for request duration.
     *
     * @var int
     */
    private static $reservedValidTime = 60;

    /**
     * @var string
     */
    private static $endpointUrl = '/accessToken/get';

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var UrlResolver
     */
    private $urlResolver;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ScopeResolverInterface
     */
    private $scopeResolver;

    /**
     * @var array
     */
    private $jwtRecord = [];

    /**
     * @var CurlFactory
     */
    private $adapterFactory;

    /**
     * @param ResourceConnection $resourceConnection
     * @param CurlFactory $adapterFactory
     * @param ConfigInterface $config
     * @param Json $serializer
     * @param LoggerInterface $logger
     * @param UrlResolver $urlResolver
     * @param ScopeResolverInterface $scopeResolver
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        CurlFactory $adapterFactory,
        ConfigInterface $config,
        Json $serializer,
        LoggerInterface $logger,
        UrlResolver $urlResolver,
        ScopeResolverInterface $scopeResolver
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->adapterFactory = $adapterFactory;
        $this->config = $config;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->urlResolver = $urlResolver;
        $this->scopeResolver = $scopeResolver;
    }

    /**
     * {@inheritdoc}
     *
     * @param null $scopeId
     *
     * @return mixed|string
     * @throws AuthenticationException
     * @throws CouldNotSaveException
     */
    public function get($scopeId = null)
    {
        $this->loadTokenRecord($scopeId);
        if (!$this->isValidToken()) {
            $this->regenerate($scopeId);
        }
        return $this->jwtRecord['access_token'];
    }

    /**
     * Method to check token validation time.
     *
     * @return bool
     */
    private function isValidToken(): bool
    {
        if (!empty($this->jwtRecord) &&
            ($this->jwtRecord['expires_on'] > time() + self::$reservedValidTime)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Method to regenerate access token from Vipps and save it to storage.
     *
     * @param int|null $scopeId
     *
     * @throws CouldNotSaveException
     * @throws AuthenticationException
     */
    public function regenerate($scopeId = null)
    {
        $jwt = $this->readJwt($scopeId);
        $this->refreshJwt($jwt, $scopeId);
    }

    /**
     * Method to authenticate into Vipps API to retrieve access token(Json Web Token).
     *
     * @param int $scopeId
     *
     * @return array
     * @throws AuthenticationException
     */
    private function readJwt($scopeId)
    {
        try {
            $adapter = null;
            /** @var MagentoCurl $adapter */
            $adapter = $this->adapterFactory->create();
            $headers = [
                Curl::HEADER_PARAM_CLIENT_ID . ': '
                    . $this->config->getValue('client_id', $this->getScopeId($scopeId)),
                Curl::HEADER_PARAM_CLIENT_SECRET . ': '
                    . $this->config->getValue('client_secret', $this->getScopeId($scopeId)),
                Curl::HEADER_PARAM_SUBSCRIPTION_KEY . ': '
                    . $this->config->getValue('subscription_key1', $this->getScopeId($scopeId)),
                'Content-Type: application/json',
                'Content-Length: 0'
            ];
            // send request
            $adapter->write('POST', $this->urlResolver->getUrl(self::$endpointUrl), '1.1', $headers);
            $response = Response::fromString($adapter->read());
            $jwt = $this->serializer->unserialize($response->getBody());
            if (!$response->isSuccess()) {
                throw new \Exception($response->getBody()); //@codingStandardsIgnoreLine
            }
            if (!$this->isJwtValid($jwt)) {
                throw new \Exception('Not valid JWT data returned from Vipps. Response: '. $response->toString()); //@codingStandardsIgnoreLine
            }
        } catch (\Exception $e) {    //@codingStandardsIgnoreLine
            $this->logger->critical($e->getMessage());
            throw new AuthenticationException(__('Can\'t retrieve access token from Vipps.'), $e);
        } finally {
            $adapter ? $adapter->close() : null;
        }

        return $jwt;
    }

    /**
     * Method to load latest token record from storage.
     *
     * @return array
     */
    private function loadTokenRecord($scopeId)
    {
        if (!$this->jwtRecord) {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select(); //@codingStandardsIgnoreLine
            $select->from($this->resourceConnection->getTableName('vipps_payment_jwt')) //@codingStandardsIgnoreLine
                ->where('scope = ?', ScopeInterface::SCOPE_STORE) // @codingStandardsIgnoreLine
                ->where('scope_id = ?', $this->getScopeId($scopeId)) //@codingStandardsIgnoreLine
                ->limit(1) //@codingStandardsIgnoreLine
                ->order("token_id DESC"); //@codingStandardsIgnoreLine
            $this->jwtRecord = $connection->fetchRow($select) ?: []; //@codingStandardsIgnoreLine
        }
        return $this->jwtRecord;
    }

    /**
     * Method to update token in storage if token_id was specified
     * and insert a new one in another case.
     *
     * @param $jwt
     * @param $scopeId
     *
     * @throws CouldNotSaveException
     */
    private function refreshJwt($jwt, $scopeId)
    {
        $connection = $this->resourceConnection->getConnection();
        try {
            /** Merge jwtRecord to save token_id for updating record in db */
            $this->jwtRecord = array_merge($this->jwtRecord, $jwt);
            if (isset($this->jwtRecord['token_id'])) {
                $connection->update(
                    $this->resourceConnection->getTableName('vipps_payment_jwt'),
                    $this->jwtRecord,
                    'token_id = ' . $this->jwtRecord['token_id']
                );
            } else {
                $this->jwtRecord['scope'] = ScopeInterface::SCOPE_STORE;
                $this->jwtRecord['scope_id'] = $this->getScopeId($scopeId);
                $connection->insert( //@codingStandardsIgnoreLine
                    $this->resourceConnection->getTableName('vipps_payment_jwt'),
                    $this->jwtRecord
                );
            }
            $this->logger->debug(__('Refreshed Jwt data.'));
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            throw new CouldNotSaveException(__('Can\'t save jwt data to database.' . $e->getMessage()));
        }
    }

    /**
     * Method to validate JWT token.
     *
     * @param $jwt
     *
     * @return bool
     */
    private function isJwtValid($jwt): bool
    {
        $requiredKeys = [
            'token_type', 'expires_in', 'ext_expires_in', 'not_before', 'resource', 'access_token'
        ];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $jwt)) {
                return false;
            }
        }
        return true;
    }

    private function getScopeId($scopeId = null)
    {
        return $scopeId ?? $this->scopeResolver->getScope()->getId();
    }
}
