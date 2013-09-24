<?php

namespace Message\Mothership\Commerce\Task\Porting;

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

		foreach($result as $row) {
			$new->add('
				INSERT INTO
					order_item_personalisation
				(
					item_id,
					sender_name,
					recipient_name,
					recipient_email,
					message
				)
				VALUES
				(
					:item_id?,
					:sender_name?,
					:recipient_name?,
					:recipient_email?,
					:message?
				)', (array) $row
			);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order item personalisations</info>');
		}

		return true;
    }
}