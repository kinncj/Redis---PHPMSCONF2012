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

use Predis\Profile\ServerProfileInterface;

/**
 * Interface that must be implemented by classes that provide their own mechanism
 * to create and initialize new instances of Predis\Connection\SingleConnectionInterface.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
interface ConnectionFactoryInterface
{
    /**
     * Defines or overrides the connection class identified by a scheme prefix.
     *
     * @param string $scheme URI scheme identifying the connection class.
     * @param mixed $initializer FQN of a connection class or a callable object for lazy initialization.
     */
    public function define($scheme, $initializer);

    /**
     * Undefines the connection identified by a scheme prefix.
     *
     * @param string $scheme Parameters for the connection.
     */
    public function undefine($scheme);

    /**
     * Creates a new connection object.
     *
     * @param mixed $parameters Parameters for the connection.
     * @return Predis\Connection\SingleConnectionInterface
     */
    public function create($parameters, ServerProfileInterface $profile = null);

    /**
     * Prepares an aggregation of connection objects.
     *
     * @param AggregatedConnectionInterface Instance of an aggregated connection class.
     * @param array $parameters List of parameters for each connection object.
     * @return Predis\Connection\AggregatedConnectionInterface
     */
    public function createAggregated(AggregatedConnectionInterface $cluster, $parameters, ServerProfileInterface $profile = null);
}
