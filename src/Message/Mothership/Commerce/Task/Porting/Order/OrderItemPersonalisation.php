<?php

namespace Message\Mothership\Commerce\Task\Porting\Order;

use Message\Mothership\Commerce\Task\Porting\Porting;

class OrderItemPersonalisation extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					item_id,
					sender_name,
					recipient_name,
					recipient_email,
					message
				FROM
					order_item_personalisation';

		$result = $old->run($sql);
		$output= '';
		$new->add('TRUNCATE order_item_personalisation');

		foreach ($result as $row) {
			if ($row->sender_name) {
				$new->add('
					INSERT INTO
						order_item_personalisation
					SET
						item_id = :itemID?i,
						name    = :name?s,
						value   = :value?sn
				', array(
					'itemID' => $row->item_id,
					'name'   => 'sender_name',
					'value'  => $row->sender_name,
				));
			}

			if ($row->recipient_name) {
				$new->add('
					INSERT INTO
						order_item_personalisation
					SET
						item_id = :itemID?i,
						name    = :name?s,
						value   = :value?sn
				', array(
					'itemID' => $row->item_id,
					'name'   => 'recipient_name',
					'value'  => $row->recipient_name,
				));
			}

			if ($row->recipient_email) {
				$new->add('
					INSERT INTO
						order_item_personalisation
					SET
						item_id = :itemID?i,
						name    = :name?s,
						value   = :value?sn
				', array(
					'itemID' => $row->item_id,
					'name'   => 'recipient_email',
					'value'  => $row->recipient_email,
				));
			}

			if ($row->message) {
				$new->add('
					INSERT INTO
						order_item_personalisation
					SET
						item_id = :itemID?i,
						name    = :name?s,
						value   = :value?sn
				', array(
					'itemID' => $row->item_id,
					'name'   => 'message',
					'value'  => $row->message,
				));
			}
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order item personalisations</info>');
		}

		return true;
    }
}