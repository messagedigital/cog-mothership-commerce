<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1383562252_UpdateOrderDispatch extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `order_dispatch`
			ADD `address_id` int(11) unsigned NOT NULL AFTER `order_id`;
		");
	}

	public function down()
	{
		$this->run('
			ALTER TABLE `order_dispatch` DROP `address_id`;
		');
	}
}
