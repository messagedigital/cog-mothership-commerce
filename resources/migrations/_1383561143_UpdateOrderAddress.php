<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1383561143_UpdateOrderAddress extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `order_address`
			ADD `created_at` int(11) unsigned NOT NULL AFTER `order_id`;
		");
	}

	public function down()
	{
		$this->run('
			ALTER TABLE `order_address` DROP `created_at`;
		');
	}
}
