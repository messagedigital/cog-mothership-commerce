<?php

namespace Message\Mothership\Commerce\Task\Porting;

class OrderItemDiscount extends Porting
{
    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					item_id,
					campaign_id AS discount_id,
					item_discount AS amount
				FROM
					order_item
				JOIN
					order_discount USING (order_id)
				JOIN
					val_campaign ON (val_campaign.campaign_code = order_discount.discount_id)
				WHERE item_discount > 0
				GROUP BY item_id';

		$result = $old->run($sql);
		$output= '';
		$new->add('TRUNCATE order_item_discount');

		foreach($result as $row) {
			$new->add('
				INSERT INTO
					order_item_discount
				(
					item_id,
					discount_id,
					amount
				)
				VALUES
				(
					:item_id?,
					:discount_id?,
					:amount?
				)', (array) $row
			);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order item discounts</info>');
		}

		return true;
    }
}