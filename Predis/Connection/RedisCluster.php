<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Connection;

use Predis\ClientException;
use Predis\NotSupportedException;
use Predis\ResponseErrorInterface;
use Predis\Command\CommandInterface;
use Predis\Command\Hash\RedisClusterHashStrategy;
use Predis\Connection\ConnectionFactory;
use Predis\Connection\ConnectionFactoryInterface;
use Predis\Distribution\CRC16HashGenerator;

/**
 * Abstraction for Redis cluster (Redis v3.0).
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class RedisCluster implements ClusterConnectionInterface, \IteratorAggregate, \Countable
{
    private $pool;
    private $slots;
    private $slotsMap;
    private $connections;
    private $distributor;
    private $cmdHasher;

    /**
     * @param ConnectionFactoryInterface $connections Connection factory object.
     */
    public function __construct(ConnectionFactoryInterface $connections = null)
    {
        $this->pool = array();
        $this->slots = array();
        $this->slotsMap = array();
        $this->connections = $connections ?: new ConnectionFactory();
        $this->distributor = new CRC16HashGenerator();
        $this->cmdHasher = new RedisClusterHashStrategy();
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected()
    {
        foreach ($this->pool as $connection) {
            if ($connection->isConnected()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        foreach ($this->pool as $connection) {
            $connection->connect();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        foreach ($this->pool as $connection) {
            $connection->disconnect();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function add(SingleConnectionInterface $connection)
    {
        $parameters = $connection->getParameters();
        $this->pool["{$parameters->host}:{$parameters->port}"] = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(SingleConnectionInterface $connection)
    {
        if (($id = array_search($connection, $this->pool, true)) !== false) {
            unset($this->pool[$id]);

            return true;
        }

        return false;
    }

    /**
     * Removes a connection instance using its alias or index.
     *
     * @param string $connectionId Alias or index of a connection.
     * @return Boolean Returns true if the connection was in the pool.
     */
    public function removeById($connectionId)
    {
        if (isset($this->pool[$connectionId])) {
            unset($this->pool[$connectionId]);

            return true;
        }

        return false;
    }

    /**
     * Preassociate a connection to a set of slots to avoid runtime guessing.
     *
     * @todo Check type or existence of the specified connection.
     *
     * @param int $first Initial slot.
     * @param int $last Last slot.
     * @param ConnectionSingleInterface|string $connection ID or connection instance.
     */
    public function setSlots($first, $last, $connection)
    {
        $connection = (string) $connection;

        if ($first < 0 || $first > 4095 || $last < 0 || $last > 4095 || $last < $first) {
            throw new \OutOfBoundsException("Invalid slot values for $connection: [$first-$last]");
        }

        $slots = array_fill($first, $last - $first + 1, $connection);
        $this->slotsMap = array_merge($this->slotsMap, $slots);
    }

    /**
     * {@inheritdoc}
     */
    public function getConnection(CommandInterface $command)
    {
        $hash = $command->getHash();

        if (!isset($hash)) {
            $hash = $this->cmdHasher->getHash($this->distributor, $command);

            if (!isset($hash)) {
                throw new NotSupportedException("Cannot send {$command->getId()} commands to redis-cluster");
            }

            $command->setHash($hash);
        }

        $slot = $hash & 4095; // 0x0FFF

        if (isset($this->slots[$slot])) {
            return $this->slots[$slot];
        }

        if (isset($this->slotsMap[$slot])) {
            $this->slots[$slot] = $connection = $this->pool[$this->slotsMap[$slot]];
        }

        $connection = $this->pool[isset($this->slotsMap[$slot]) ? $this->slotsMap[$slot] : array_rand($this->pool)];
        $this->slots[$slot] = $connection;

        return $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function getConnectionById($id = null)
    {
        if (!isset($id)) {
            throw new \InvalidArgumentException("A valid connection ID must be specified");
        }

        return isset($this->pool[$id]) ? $this->pool[$id] : null;
    }

    /**
     * Handles -MOVED or -ASK replies by re-executing the command on the server
     * specified by the Redis reply.
     *
     * @param CommandInterface $command Command that generated the -MOVE or -ASK reply.
     * @param string $request Type of request (either 'MOVED' or 'ASK').
     * @param string $details Parameters of the MOVED/ASK request.
     * @return mixed
     */
    protected function onMoveRequest(CommandInterface $command, $request, $details)
    {
        list($slot, $host) = explode(' ', $details, 2);
        $connection = $this->getConnectionById($host);

        if (!isset($connection)) {
            $parameters = array('host' => null, 'port' => null);
            list($parameters['host'], $parameters['port']) = explode(':', $host, 2);
            $connection = $this->connections->create($parameters);
        }

        switch ($request) {
            case 'MOVED':
                $this->add($connection);
                $this->slots[(int) $slot] = $connection;
                return $this->executeCommand($command);

            case 'ASK':
                return $connection->executeCommand($command);

            default:
                throw new ClientException("Unexpected request type for a move request: $request");
        }
    }

    /**
     * Returns the underlying command hash strategy used to hash
     * commands by their keys.
     *
     * @return CommandHashStrategy
     */
    public function getCommandHashStrategy()
    {
        return $this->cmdHasher;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->pool);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->pool);
    }

    /**
     * Handles -ERR replies from Redis.
     *
     * @param CommandInterface $command Command that generated the -ERR reply.
     * @param ResponseErrorInterface $error Redis error reply object.
     * @return mixed
     */
    protected function handleServerError(CommandInterface $command, ResponseErrorInterface $error)
    {
        list($type, $details) = explode(' ', $error->getMessage(), 2);

        switch ($type) {
            case 'MOVED':
            case 'ASK':
                return $this->onMoveRequest($command, $type, $details);

            default:
                return $error;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function writeCommand(CommandInterface $command)
    {
        $this->getConnection($command)->writeCommand($command);
    }

    /**
     * {@inheritdoc}
     */
    public function readResponse(CommandInterface $command)
    {
        return $this->getConnection($command)->readResponse($command);
    }

    /**
     * {@inheritdoc}
     */
    public function executeCommand(CommandInterface $command)
    {
        $connection = $this->getConnection($command);
        $reply = $connection->executeCommand($command);

        if ($reply instanceof ResponseErrorInterface) {
            return $this->handleServerError($command, $reply);
        }

        return $reply;
    }
}
