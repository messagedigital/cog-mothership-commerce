<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1383147378_CreateStockSnapshot extends Migration
{
	public function up()
	{
		$this->run("
			CREATE TABLE IF NOT EXISTS `product_unit_stock_snapshot` (
			  `unit_id` int(11) unsigned NOT NULL,
			  `location` varchar(255) NOT NULL DEFAULT '0',
			  `stock` int(11) DEFAULT NULL,
			  `created_at` int(11) DEFAULT NULL,
			  KEY `unit_id` (`unit_id`,`location`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
	}

	public function down()
	{
		$this->run('
			DROP TABLE IF EXISTS
				`product_unit_stock_snapshot`
		');
	}
}
