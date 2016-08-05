<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1449670331_MakeImageOptionLowerCase extends Migration
{
	public function up()
	{
		$this->run("
			UPDATE
				product_image_option
			SET
				`name` = LOWER(`name`)
			;
		");
	}

	public function down()
	{
		// Cannot be rolled back
	}
}