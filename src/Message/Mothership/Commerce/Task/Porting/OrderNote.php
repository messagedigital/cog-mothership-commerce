<?php

namespace Message\Mothership\Commerce\Task\Porting;

class OrderNote extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					note_id,
					order_id,
					UNIX_TIMESTAMP(note_datetime) AS created_at,
					user_id AS created_by,
					note,
					notify_customer AS customer_notified,
					raised_from
				FROM
					order_note';

		$result = $old->run($sql);
		$output= '';
		$new->add('TRUNCATE order_note');

		foreach($result as $row) {
			$new->add('
				INSERT INTO
					order_note
				(
					note_id,
					order_id,
					created_at,
					created_by,
					note,
					customer_notified,
					raised_from
				)
				VALUES
				(
					:note_id?,
					:order_id?,
					:created_at?,
					:created_by?,
					:note?,
					:customer_notified?,
					:raised_from?
				)', (array) $row
			);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order note</info>');
		}

		return true;
    }
}