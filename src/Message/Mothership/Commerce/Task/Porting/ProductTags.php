<?php

namespace Message\Mothership\Commerce\Task\Porting;

class ProductTags extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					catalogue_id AS product_id,
					range_name AS name
				FROM
					catalogue_range
				JOIN val_range USING (range_id)
				WHERE
					range_name != \'\'';

		$result = $old->run($sql);
		$new->add('TRUNCATE product_tag');
		$output= '';
		foreach($result as $row) {
			$new->add('
				INSERT INTO
					product_tag
				(
					product_id,
					name
				)
				VALUES
				(
					:product_id?,
					:name?
				)', (array) $row);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported product tags</info>');
		}

		return true;
    }
}