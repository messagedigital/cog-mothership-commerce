<?php

namespace Message\Mothership\Commerce\Task\Porting;

class OrderSummary extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					order_summary.order_id,
					UNIX_TIMESTAMP(order_summary.order_datetime) AS created_at,
					user_id AS created_by,
					UNIX_TIMESTAMP(order_summary.order_updated) AS updated_at,
					NULL AS update_by,
					CASE order_summary.status_id
						WHEN 0 THEN 0
						WHEN 1 THEN 500
						WHEN 2 THEN 500
						WHEN 3 THEN 500
						WHEN 4 THEN 500
						WHEN 5 THEN 800
						WHEN 6 THEN 1000
						ELSE 1000
					END AS status_code,
					order_summary.user_id AS user_id,
					"web" AS type,
					"en_GB" AS locale,
					order_summary.order_taxable AS taxable,
					order_summary.currency_id AS currency_id,
					0 AS conversion_rate,
					order_summary.order_total - IFNULL(order_tax,0) AS product_net,
					IFNULL(order_summary.order_discount,0) AS product_discount,
					IFNULL(order_summary.order_tax,0) AS product_tax,
					order_summary.order_total AS product_gross,
					order_summary.order_total + IFNULL(order_shipping.shipping_amount,0) - IFNULL(order_summary.order_tax,0) - IFNULL(order_shipping.shipping_tax,0) - IFNULL(order_summary.order_discount,0) - IFNULL(order_summary.order_tax_discount,0) AS total_net,
					IFNULL(order_summary.order_discount,0) + IFNULL(order_summary.order_tax,0) AS total_discount,
					IFNULL(order_shipping.shipping_tax,0) + IFNULL(order_summary.order_tax,0)  AS total_tax,
					IFNULL(order_summary.order_payment,0) AS total_gross
				FROM
					order_summary
				JOIN
					order_shipping ON (order_summary.order_id = order_shipping.order_id)';

		$new->add('TRUNCATE order_summary');
		$result = $old->run($sql);
		$output= '';
		foreach($result as $row) {
			$new->add('
				INSERT INTO
					order_summary
				(
					order_id,
					created_at,
					created_by,
					updated_at,
					updated_by,
					status_code,
					user_id,
					type,
					locale,
					taxable,
					currency_id,
					conversion_rate,
					product_net,
					product_discount,
					product_tax,
					product_gross,
					total_net,
					total_discount,
					total_tax,
					total_gross
				)
				VALUES
				(
					:order_id?,
					:created_at?,
					:created_by?,
					:updated_at?,
					:updated_by?,
					:status_code?,
					:user_id?,
					:type?,
					:locale?,
					:taxable?,
					:currency_id?,
					:conversion_rate?,
					:product_net?,
					:product_discount?,
					:product_tax?,
					:product_gross?,
					:total_net?,
					:total_discount?,
					:total_tax?,
					:total_gross
				)', (array) $row
			);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order summary</info>');
		}

		return true;
    }
}