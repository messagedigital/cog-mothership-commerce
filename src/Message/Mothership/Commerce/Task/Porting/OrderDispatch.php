<?php

namespace Message\Mothership\Commerce\Task\Porting;

class OrderDispatch extends Porting
{
    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					despatch_id AS dispatch_id,
					order_id,
					IFNULL(
						UNIX_TIMESTAMP(despatch_timestamp),
						UNIX_TIMESTAMP(order_datetime)) AS created_at,
					NULL AS created_by,
					NULL AS updated_at,
					NULL AS updated_by,
					shipped_at AS shipped_at,
					shipped_by AS shipped_by,
					despatch_type_name AS method,
					despatch_code AS `code`,
					despatch_cost AS cost,
					despatch_weight AS weight_grams
				FROM
					order_despatch
				JOIN
					order_summary USING (order_id)
				JOIN
					order_despatch_type USING (despatch_type_id)
				LEFT JOIN (
					SELECT
						order_id,
						UNIX_TIMESTAMP(status_datetime) AS shipped_at,
						staff_id AS shipped_by
					FROM
						order_item_status
					WHERE status_id = 6
					GROUP BY order_id
					ORDER BY status_datetime DESC
				) AS shipped_date USING (order_id)';

		$result = $old->run($sql);
		$output= '';
		$new->add('TRUNCATE order_dispatch');

		foreach($result as $row) {

			if ($row->method == 'fedex uk') {
				$row->method = 'fedex-uk';
			} else {
				$row->method = 'fedex-express';
			}

			$new->add('
				INSERT INTO
					order_dispatch
				(
					dispatch_id,
					order_id,
					created_at,
					created_by,
					updated_at,
					updated_by,
					shipped_at,
					shipped_by,
					method,
					code,
					cost,
					weight_grams
				)
				VALUES
				(
					:dispatch_id?,
					:order_id?,
					:created_at?,
					:created_by?,
					:updated_at?,
					:updated_by?,
					:shipped_at?,
					:shipped_by?,
					:method?,
					:code?,
					:cost?,
					:weight_grams?
				)', (array) $row
			);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order dispatches</info>');
		}

		return true;
    }
}