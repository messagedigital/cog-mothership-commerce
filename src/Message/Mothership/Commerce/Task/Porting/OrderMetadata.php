<?php

namespace Message\Mothership\Commerce\Task\Porting;

class OrderMetadata extends Porting
{
    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					order_id,
					metadata_key AS `key`,
					metadata_value AS `value`
				FROM
					order_metadata';

		$result = $old->run($sql);
		$output= '';
		$new->add('TRUNCATE order_metadata');

		foreach($result as $row) {
			$new->add('
				INSERT INTO
					order_metadata
				(
					order_id,
					`key`,
					`value`
				)
				VALUES
				(
					:order_id?,
					:key?,
					:value?
				)', (array) $row
			);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order metadata</info>');
		}

		return true;
    }
}