<?php

namespace Message\Mothership\Commerce\Task\Porting;

class OrderDiscount extends Porting
{
    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					NULL AS discount_id,
					order_id,
					UNIX_TIMESTAMP(order_datetime) AS created_at,
					NULL AS created_by,
					discount_id AS `code`,
					IFNULL(discount_amount, order_discount) AS amount,
					discount_percentage AS percentage,
					campaign_name AS `name`,
					campaign_description AS description
				FROM
					order_discount
				JOIN
					order_summary USING (order_id)
				JOIN val_campaign ON (val_campaign.campaign_code = order_discount.discount_id)
				GROUP BY order_id';

		$result = $old->run($sql);
		$output= '';
		$new->add('TRUNCATE order_discount');

		foreach($result as $row) {
			$new->add('
				INSERT INTO
					order_discount
				(
					discount_id,
					order_id,
					created_at,
					created_by,
					code,
					amount,
					percentage,
					name,
					description
				)
				VALUES
				(
					:discount_id?,
					:order_id?,
					:created_at?,
					:created_by?,
					:code?,
					:amount?,
					:percentage?,
					:name?,
					:description?
				)', (array) $row
			);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order discount</info>');
		}

		return true;
    }
}