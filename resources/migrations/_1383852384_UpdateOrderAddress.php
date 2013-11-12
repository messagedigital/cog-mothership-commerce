<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1383852384_UpdateOrderAddress extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `order_address`
			MODIFY COLUMN `created_by` int(11) unsigned NULL;
		");
	}

	public function down()
	{
		$this->run('
			ALTER TABLE `order_address`
			MODIFY COLUMN `created_by` int(11) unsigned NOT NULL;
		');
	}
}
