<?php

namespace Message\Mothership\Commerce\Task\Porting;

class ProductExport extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					catalogue_id AS product_id,
					\'en_GB\' AS locale,
					export_value,
					export_description,
					export_manufacture_country_id
				FROM
					catalogue_export';

		$result = $old->run($sql);
		$new->add('TRUNCATE product_export');
		$output= '';
		foreach($result as $row) {
			$new->add('
				INSERT INTO
					product_export
				(
					product_id,
					locale,
					export_value,
					export_description,
					export_manufacture_country_id
				)
				VALUES
				(
					:product_id?,
					:locale?,
					:export_value?,
					:export_description?,
					:export_manufacture_country_id?
				)', (array) $row);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported product export</info>');
		}

		return true;
    }
}