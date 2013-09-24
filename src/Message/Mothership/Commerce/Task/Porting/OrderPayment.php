<?php

namespace Message\Mothership\Commerce\Task\Porting;

class OrderPayment extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					payment_id,
					order_id,
					NULL AS return_id,
					UNIX_TIMESTAMP(payment_datetime) AS created_at,
					NULL AS created_by,
					payment_type_name AS method,
					payment_amount AS amount,
					payment_reference AS reference
				FROM
					order_payment
				JOIN order_payment_type USING (payment_type_id)';

		$result = $old->run($sql);
		$output= '';
		$new->add('TRUNCATE order_payment');

		foreach($result as $row) {
			$new->add('
				INSERT INTO
					order_payment
				(
					payment_id,
					order_id,
					return_id,
					created_at,
					created_by,
					method,
					amount,
					reference
				)
				VALUES
				(
					:payment_id?,
					:order_id?,
					:return_id?,
					:created_at?,
					:created_by?,
					:method?,
					:amount?,
					:reference?
				)', (array) $row
			);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order payment</info>');
		}

		return true;
    }
}