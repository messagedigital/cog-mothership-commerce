<?php

namespace Message\Mothership\Commerce\Task\Porting;

class OrderItem extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					item_id AS item_id,
					order_id AS order_id,
					UNIX_TIMESTAMP(order_datetime) AS created_at,
					NULL AS created_by,
					item_original_price AS list_price,
					item_price - IFNULL(item_tax, 0) AS net,
					IFNULL(item_discount,0) AS discount,
					IFNULL(item_tax,0) AS tax,
					item_price AS gross,
					IFNULL(item_rrp, item_price) AS rrp,
					item_tax_rate AS tax_rate,
					item_tax_rate AS product_tax_rate,
					\'inclusive\' AS tax_strategy,
					product_id AS product_id,
					product_name AS product_name,
					unit_id AS unit_id,
					1 AS unit_revision,
					unit_name AS sku,
					barcode AS barcode,
					item_description_localised AS options,
					brand_name AS brand,
					item_weight AS weight_grams,
					\'web\' AS stock_location
				FROM
					order_item
				JOIN order_summary USING (order_id)';

		$result = $old->run($sql);
		$output= '';

		$new->add('TRUNCATE order_item');
		foreach($result as $row) {
			$new->add('
				INSERT INTO
					order_item
				(
					item_id,
					order_id,
					created_at,
					created_by,
					list_price,
					net,
					discount,
					tax,
					gross,
					rrp,
					tax_rate,
					product_tax_rate,
					tax_strategy,
					product_id,
					product_name,
					unit_id,
					unit_revision,
					sku,
					barcode,
					options,
					brand,
					weight_grams,
					stock_location
				)
				VALUES
				(
					:item_id?,
					:order_id?,
					:created_at?,
					:created_by?,
					:list_price?,
					:net?,
					:discount?,
					:tax?,
					:gross?,
					:rrp?,
					:tax_rate?,
					:product_tax_rate?,
					:tax_strategy?,
					:product_id?,
					:product_name?,
					:unit_id?,
					:unit_revision?,
					:sku?,
					:barcode?,
					:options?,
					:brand?,
					:weight_grams?,
					:stock_location?
				)', (array) $row
			);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order items</info>');
		}

		return true;
    }
}