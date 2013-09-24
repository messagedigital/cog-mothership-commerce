<?php

namespace Message\Mothership\Commerce\Task\Porting;

class OrderRefund extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					order_refund.refund_id,
					order_refund.order_id,
					MAX(payment_id) AS payment_id,
					return_id,
					UNIX_TIMESTAMP(return_datetime) AS created_at,
					NULL AS created_by,
					payment_type_name AS method,
					refund_amount AS amount,
					refund_reason_name AS reason,
					refund_reference AS reference
				FROM
					order_refund
				JOIN
					order_item_return USING (refund_id)
				JOIN
					order_payment_type USING (payment_type_id)
				JOIN
					order_refund_reason USING (refund_reason_id)
				LEFT JOIN
					order_payment ON (order_payment.order_id = order_refund.order_id AND payment_amount = refund_amount)
				GROUP BY
					order_refund.refund_id';

		$result = $old->run($sql);
		$output= '';
		$new->add('TRUNCATE order_refund');

		foreach($result as $row) {
			$new->add('
				INSERT INTO
					order_refund
				(
					refund_id,
					order_id,
					payment_id,
					return_id,
					created_at,
					created_by,
					method,
					amount,
					reason,
					reference
				)
				VALUES
				(
					:refund_id?,
					:order_id?,
					:payment_id?,
					:return_id?,
					:created_at?,
					:created_by?,
					:method?,
					:amount?,
					:reason?,
					:reference?
				)', (array) $row
			);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order refund</info>');
		}

		return true;
    }
}