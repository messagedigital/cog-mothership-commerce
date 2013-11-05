<?php

namespace Message\Mothership\Commerce\Product\Stock;

use Message\Cog\Console\Task\Task;

class StockSnapshot extends Task
{
    protected function configure()
    {
        // Run once a day at midnight
        $this->schedule('0 0 * * *');
    }

	public function process()
	{
		$query = $this->get('db.query');

		$result = $query->run("
			INSERT INTO
				product_unit_stock_snapshot (
					unit_id,
					location,
					stock,
					created_at
				)
			SELECT
				unit_id,
				location,
				stock,
				UNIX_TIMESTAMP()
			FROM
				product_unit_stock

		");

		if ($result) {
			$this->writeln('<info>Successfully cached a snapshot of current stock levels.</info>');
			return true;
		}

		$this->writeln('<error>Failed to cache snapshot of current stock levels.</error>');
		return false;
	}
}