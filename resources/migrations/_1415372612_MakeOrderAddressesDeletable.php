<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1415372612_MakeOrderAddressesDeletable extends Migration
{
	public function up()
	{
		$this->run("ALTER TABLE order_address ADD deleted_at INT(11) UNSIGNED  NULL  DEFAULT NULL  AFTER created_by; ");
		$this->run("ALTER TABLE order_address ADD deleted_by INT(11) UNSIGNED  NULL  DEFAULT NULL  AFTER deleted_at; ");
		$this->run("
			UPDATE order_address
			LEFT JOIN
				(SELECT
					address_id,
					(SELECT
						MIN(created_at)
					FROM order_address sub
					WHERE sub.order_id = main.order_id AND sub.address_id > main.address_id
					AND sub.type = main.type ) AS nxttime
				FROM order_address AS main) AS deleted_at
				ON order_address.address_id = deleted_at.address_id
			SET order_address.deleted_at = deleted_at.nxttime; ");
		$this->run("
			UPDATE order_address
				LEFT JOIN
					(SELECT
						a.address_id,
						b.created_by
					FROM order_address a
					LEFT JOIN (
						SELECT
							address_id,
							order_id,
							created_at,
							created_by
						FROM order_address
					) b ON (a.deleted_at = b.created_at AND a.order_id = b.order_id)
					HAVING b.created_by IS NOT NULL) c ON order_address.address_id = c.address_id
				SET order_address.deleted_by = c.created_by; ");
	}

	public function down()
	{
		$this->run("ALTER TABLE order_address DROP COLUMN deleted_at; ");
		$this->run("ALTER TABLE order_address DROP COLUMN deleted_by; ");
	}
}