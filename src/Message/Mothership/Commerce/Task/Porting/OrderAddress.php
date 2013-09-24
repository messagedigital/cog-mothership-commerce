<?php

namespace Message\Mothership\Commerce\Task\Porting;

class OrderAddress extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$newQuery = new \Message\Cog\DB\Query($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					NULL AS address_id,
					order_id,
					CASE address_type_id
						WHEN 1 THEN \'delivery\'
						WHEN 2 THEN \'billing\'
					END AS type,
					address_name,
					address_address_1 AS line_1,
					address_address_2 AS line_2,
					NULL AS line_3,
					NULL AS line_4,
					address_postcode AS postcode,
					address_country AS country,
					address_country_id AS country_id,
					address_telephone AS telephone,
					address_town AS town,
					address_state_id AS state_id,
					address_state AS state
				FROM
					order_address';

		$result = $old->run($sql);
		$output= '';

		$new->add('TRUNCATE order_address');

		foreach($result as $row) {
			if ($row->order_id >= 5000) {
				$nameParts     = explode(' ',$row->address_name);
				$row->forename = $nameParts[1];
				$row->surname  = $nameParts[2];
				$row->title    = $nameParts[0];
			} else {
				$nameParts     = explode(' ',$row->address_name);
				$row->forename = $nameParts[0];
				$row->surname  = $nameParts[1];
				$row->title    = null;
			}

			$new->add('
				INSERT INTO
					order_address
				(
					address_id,
					order_id,
					type,
					title,
					forename,
					surname,
					line_1,
					line_2,
					line_3,
					line_4,
					postcode,
					country,
					country_id,
					telephone,
					town,
					state_id,
					state
				)
				VALUES
				(
					:address_id?,
					:order_id?,
					:type?,
					:title?,
					:forename?,
					:surname?,
					:line_1?,
					:line_2?,
					:line_3?,
					:line_4?,
					:postcode?,
					:country?,
					:country_id?,
					:telephone?,
					:town?,
					:state_id?,
					:state?
				)', array(
					'address_id' => $row->address_id,
					'order_id'   => $row->order_id,
					'type'       => $row->type,
					'title'      => $row->title,
					'forename'   => $row->forename,
					'surname'    => $row->surname,
					'line_1'     => $row->line_1,
					'line_2'     => $row->line_2,
					'line_3'     => $row->line_3,
					'line_4'     => $row->line_4,
					'postcode'   => $row->postcode,
					'country'    => $row->country,
					'country_id' => $row->country_id,
					'telephone'  => $row->telephone,
					'town'       => $row->town,
					'state_id'   => $row->state_id,
					'state'      => $row->state
				)
			);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order addresses</info>');
		}

		return true;
    }
}