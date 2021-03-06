<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis;

use Predis\Connection\ConnectionInterface;
use Predis\Option\ClientOptionsInterface;
use Predis\Profile\ServerProfileInterface;

/**
 * Interface defining the most important parts needed to create an
 * high-level Redis client object that can interact with other
 * building blocks of Predis.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
interface ClientInterface extends BasicClientInterface
{
    /**
     * Returns the server profile used by the client.
     *
     * @return ServerProfileInterface
     */
    public function getProfile();

    /**
     * Returns the client options specified upon initialization.
     *
     * @return ClientOptionsInterface
     */
    public function getOptions();

    /**
     * Opens the connection to the server.
     */
    public function connect();

    /**
     * Disconnects from the server.
     */
    public function disconnect();

    /**
     * Returns the underlying connection instance or, when connected to a cluster,
     * one of the connection instances identified by its alias.
     *
     * @param string $id The alias of a connection when connected to a cluster.
     * @return ConnectionInterface
     */
    public function getConnection($id = null);

    /**
     * Creates a new instance of the specified Redis command.
     *
     * @param string $method The name of a Redis command.
     * @param array $arguments The arguments for the command.
     * @return CommandInterface
     */
    public function createCommand($method, $arguments = array());
}
