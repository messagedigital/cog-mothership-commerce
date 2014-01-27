<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1390483991_UpdateForexRate extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `forex_rate`
			ADD COLUMN `updated_at` int(11) unsigned NULL AFTER `created_at`;
		");
	}

	public function down()
	{
		$this->run('
			ALTER TABLE `forex_rate`
			DROP COLUMN `updated_at`;
		');
	}
}
