<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1384276019_UpdateOrderDispatch extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `order_dispatch`
			MODIFY COLUMN `address_id` int(11) unsigned NOT NULL DEFAULT '0' AFTER `order_id`;
		");
	}

	public function down()
	{
		$this->run('
			ALTER TABLE `order_dispatch`
			MODIFY COLUMN `address_id` int(11) unsigned NOT NULL AFTER `order_id`;
		');
	}
}
