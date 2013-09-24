<?php

namespace Message\Mothership\Commerce\Task\Porting;

class ProductOptions extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					unit_id,
					\'size\' AS type,
					string_value AS value
				FROM
					catalogue_unit_size
				JOIN
					val_size USING (size_id)
				JOIN
					locale_string USING (string_id)';

		$result = $old->run($sql);
		$new->add('TRUNCATE product_unit_option');
		$output= '';
		foreach($result as $row) {
			$new->add('
				INSERT INTO
					product_unit_option (
						unit_id,
						option_name,
						option_value
					)
					VALUES
					(
						?,
						?,
						?
					)', (array) $row);
		}

		$sql = 'SELECT
					unit_id,
					\'colour\' AS type,
					string_value AS value
				FROM
					catalogue_unit_colour
				JOIN
					val_colour USING (colour_id)
				JOIN
					locale_string USING (string_id)';

		$result = $old->run($sql);
		$output= '';
		foreach($result as $row) {
			$new->add('
				INSERT INTO
					product_unit_option (
						unit_id,
						option_name,
						option_value
					)
					VALUES
					(
						?,
						?,
						?
					)', (array) $row);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported product options</info>');
		}

		return true;
    }
}