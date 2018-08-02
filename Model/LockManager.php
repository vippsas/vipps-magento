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
declare(strict_types=1);

namespace Vipps\Payment\Model;

use Magento\Framework\{
    App\DeploymentConfig, App\ResourceConnection, Config\ConfigOptionsListConstants, Exception\AlreadyExistsException,
    Exception\InputException, Phrase
};

/**
 * Class LockManager
 * @package Vipps\Payment\Model
 */
class LockManager implements LockManagerInterface
{
    /**
     * @var ResourceConnection 
     */
    private $resource;
    
    /**
     * @var DeploymentConfig 
     */
    private $deploymentConfig;
    
    /**
     * @var string 
     */
    private $prefix;

    /** 
     * Holds current lock name if set, otherwise false
     * @var string|false 
     */
    private $currentLock = false;

    /**
     * Database constructor.
     *
     * @param ResourceConnection $resource
     * @param DeploymentConfig $deploymentConfig
     * @param string|null $prefix
     */
    public function __construct(
        ResourceConnection $resource,
        DeploymentConfig $deploymentConfig,
        string $prefix = null
    ) {
        $this->resource = $resource;
        $this->deploymentConfig = $deploymentConfig;
        $this->prefix = $prefix;
    }

    /**
     * Sets a lock for name
     *
     * @param string $name lock name
     * @param int $timeout How long to wait lock acquisition in seconds, negative value means infinite timeout
     * @return bool
     * @throws InputException
     * @throws AlreadyExistsException
     */
    public function lock(string $name, int $timeout = -1): bool
    {
        $name = $this->addPrefix($name);

        /**
         * Before MySQL 5.7.5, only a single simultaneous lock per connection can be acquired.
         * This limitation can be removed once MySQL minimum requirement has been raised,
         * currently we support MySQL 5.6 way only.
         */
        if ($this->currentLock) {
            throw new AlreadyExistsException(
                new Phrase(
                    'Current connection is already holding lock for $1, only single lock allowed',
                    [$this->currentLock]
                )
            );
        }

        $result = (bool)$this->resource->getConnection()->query(
            "SELECT GET_LOCK(?, ?);",
            [(string)$name, (int)$timeout]
        )->fetchColumn();

        if ($result === true) {
            $this->currentLock = $name;
        }

        return $result;
    }

    /**
     * Releases a lock for name
     *
     * @param string $name lock name
     * @return bool
     * @throws InputException
     */
    public function unlock(string $name): bool
    {
        $name = $this->addPrefix($name);

        $result = (bool)$this->resource->getConnection()->query(
            "SELECT RELEASE_LOCK(?);",
            [(string)$name]
        )->fetchColumn();

        if ($result === true) {
            $this->currentLock = false;
        }

        return $result;
    }

    /**
     * Tests of lock is set for name
     *
     * @param string $name lock name
     * @return bool
     * @throws InputException
     */
    public function isLocked(string $name): bool
    {
        $name = $this->addPrefix($name);

        return (bool)$this->resource->getConnection()->query(
            "SELECT IS_USED_LOCK(?);",
            [(string)$name]
        )->fetchColumn();
    }

    /**
     * Adds prefix and checks for max length of lock name
     *
     * Limited to 64 characters in MySQL.
     *
     * @param string $name
     * @return string $name
     * @throws InputException
     */
    private function addPrefix(string $name): string
    {
        $name = $this->getPrefix() . '|' . $name;

        if (strlen($name) > 64) {
            throw new InputException(new Phrase('Lock name too long: %1...', [substr($name, 0, 64)]));
        }

        return $name;
    }

    /**
     * Get installation specific lock prefix to avoid lock conflicts
     *
     * @return string lock prefix
     */
    private function getPrefix(): string
    {
        if ($this->prefix === null) {
            $this->prefix = (string)$this->deploymentConfig->get(
                ConfigOptionsListConstants::CONFIG_PATH_DB_CONNECTION_DEFAULT
                . '/'
                . ConfigOptionsListConstants::KEY_NAME,
                ''
            );
        }

        return $this->prefix;
    }
}
