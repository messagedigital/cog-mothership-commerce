<?php

namespace Message\Mothership\Commerce\Task\Porting;

use Message\Cog\Console\Task\Task as BaseTask;
use Message\Cog\DB\Adapter\MySQLi\Connection;
use Symfony\Component\Console\Input\InputArgument;

abstract class Porting extends BaseTask
{

	protected function configure()
	{
		$this
			->addArgument(
				'old',
				InputArgument::REQUIRED,
				'please pass in the name of the service as the last parameter'
			);
	}

	/**
	 * Gets the DB connection to port the data from
	 *
	 * @return Connection 		instance of the DB Connection
	 */
	public function getFromConnection()
	{
		$serviceName = $this->getRawInput()->getArgument('old');
		$service = $this->get($serviceName);

		if (!$service instanceof Connection) {
			throw new \Exception('service must be instance of Message\Cog\DB\Adapter\MySQLi\Connection');
		}

		return $service;
	}

	/**
	 * Gets the DB connection to port the data into
	 *
	 * @return Connection 		instance of the DB Connection
	 */
	public function getToConnection()
	{
		return new \Message\Cog\DB\Adapter\MySQLi\Connection(array(
				'host'		=> $this->get('cfg')->db->hostname,
				'user'		=> $this->get('cfg')->db->user,
				'password' 	=> $this->get('cfg')->db->pass,
				'db'		=> $this->get('cfg')->db->name,
				'charset'	=> 'utf-8',
		));
	}
}