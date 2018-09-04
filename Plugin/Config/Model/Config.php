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
namespace Vipps\Payment\Plugin\Config\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\{ScopeResolverInterface, ResourceConnection};
use Magento\Config\Model\Config as CoreConfig;
use Magento\Payment\Gateway\ConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Config
 * @package Vipps\Payment\Plugin\Config\Model
 */
class Config
{
    /**
     * @var array
     */
    private static $encryptedFields = [
        'client_id', 'merchant_serial_number', 'client_secret', 'subscription_key1', 'subscription_key2'
    ];

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var ScopeResolverInterface
     */
    private $scopeResolver;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Config constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param ConfigInterface $config
     * @param LoggerInterface $logger
     * @param ScopeResolverInterface $scopeResolver
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        ConfigInterface $config,
        LoggerInterface $logger,
        ScopeResolverInterface $scopeResolver
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->scopeResolver = $scopeResolver;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param CoreConfig $subject
     * @param \Closure $proceed
     *
     * @return CoreConfig
     * @throws LocalizedException
     */
    public function aroundSave(CoreConfig $subject, \Closure $proceed)
    {
        $oldEnv = $this->config->getValue('environment');
        $proceed($subject);
        $sectionName = $subject->getSection();
        if (!$sectionName == 'payment') {
            return $subject;
        }
        $groups = $subject->getGroups();
        $fields = $groups['vipps']['groups']['vipps_required']['fields'] ?? null;
        if (!$fields) {
            return $subject;
        }
        if ($this->areCredentialsChanged($fields) || $this->isEnvironmentChanged($oldEnv, $fields)) {
            $this->deleteJwt();
        }
        return $subject;
    }

    /**
     * Method to check if vipps credentials fields were changed.
     *
     * @param $requiredFields
     *
     * @return bool
     */
    private function areCredentialsChanged($requiredFields): bool
    {
        $hasChanged = false;
        foreach (self::$encryptedFields as $fieldKey) {
            $value = $requiredFields[$fieldKey]['value'] ?? false;
            if ($value && !preg_match('/^\*+$/', (string)$value)) {
                $hasChanged = true;
                break;
            }
        }
        return $hasChanged;
    }

    /**
     *  Method to check if vipps environment was changed.
     *
     * @param $oldEnv
     * @param $fields
     *
     * @return bool
     */
    private function isEnvironmentChanged($oldEnv, $fields): bool
    {
        $newEnv = $fields['environment']['value'] ?? null;
        if ($newEnv && $newEnv != $oldEnv) {
            return true;
        }
        return false;
    }

    /**
     * Method to delete Vipps JWT token when admin setting was changed.
     *
     * @throws LocalizedException
     */
    private function deleteJwt()
    {
        $connection = $this->resourceConnection->getConnection();
        try {
            $where = 'scope_id = ' . $this->getScopeId();
            $number = $connection->delete($this->resourceConnection->getTableName('vipps_payment_jwt'), $where);
            if ($number) {
                $this->logger->debug(__('Deleted JWT data from database.'));
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            throw new LocalizedException(__('Can\'t invalidate Vipps Jwt Token. Please try again.'));
        }
    }

    /**
     * Return current scope Id.
     *
     * @return int
     */
    private function getScopeId()
    {
        return $this->scopeResolver->getScope()->getId();
    }
}
