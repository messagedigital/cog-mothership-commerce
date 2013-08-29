<?php

namespace Message\Mothership\Commerce\Task\Porting;

class ProductUnitStock extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					unit_id,
					name AS location,
					stock
				FROM
					catalogue_unit_stock
				JOIN location USING (location_id)';

		$result = $old->run($sql);
		$new->add('TRUNCATE product_unit_stock');
		$new->add('TRUNCATE product_unit_stock_snapshot');

		$output= '';
		foreach($result as $row) {

			$new->add('
				INSERT INTO
					product_unit_stock
				(
					unit_id,
					location,
					stock
				)
				VALUES
				(
					?,?,?
				)', (array) $row);
		}

		$sql = 'SELECT
					unit_id,
					LCASE(name) AS location,
					stock,
					UNIX_TIMESTAMP(snapshot_date)
				FROM
					catalogue_unit_stock_snapshot
				JOIN location USING (location_id)';

		$result = $old->run($sql);
		foreach($result as $row) {

			$new->add('
				INSERT INTO
					product_unit_stock_snapshot
				(
					unit_id,
					location,
					stock,
					created_at
				)
				VALUES
				(
					?,?,?,?
				)', (array) $row);
		}

		if ($new->commit()) {
        	$output.= '<info>Successful</info>';
		}

		return $ouput;
    }
}