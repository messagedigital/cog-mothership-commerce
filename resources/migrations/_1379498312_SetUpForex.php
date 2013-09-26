<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1379498312_SetUpForex extends Migration
{
	public function up()
	{
		$this->run("
			CREATE TABLE `forex_rate` (
			  `currency` varchar(255) NOT NULL DEFAULT '',
			  `rate` decimal(10,4) DEFAULT NULL,
			  PRIMARY KEY (`currency`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
	}

	public function down()
	{
		$this->run('
			DROP TABLE IF EXISTS
				`forex_rate`
		');
	}
}
