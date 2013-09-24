<?php

namespace Message\Mothership\Commerce\Task\Porting;

class OrderItemDispatch extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					despatch_id AS dispatch_id,
					item_id AS item_id
				FROM
					order_despatch_items';

		$result = $old->run($sql);
		$output= '';
		$new->add('TRUNCATE order_dispatch_item');

		foreach($result as $row) {
			$new->add('
				INSERT INTO
					order_dispatch_item
				(
					item_id,
					dispatch_id
				)
				VALUES
				(
					:item_id?,
					:dispatch_id?
				)', (array) $row
			);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order item dispatches</info>');
		}

		return true;
    }
}