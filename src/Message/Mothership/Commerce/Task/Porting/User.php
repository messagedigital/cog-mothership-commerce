<?php

namespace Message\Mothership\Commerce\Task\Porting;

class User extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					user_id,
					UNIX_TIMESTAMP(sign_up_date) AS created_at,
					NULL AS created_by,
					NULL AS updated_by,
					NULL AS updated_at,
					email_name AS email,
					password,
					1 AS email_confirmed,
					title_name AS title,
					user_forename AS forename,
					user_surname AS surname,
					NULL AS last_login_at,
					NULL AS password_request_at
				FROM
					val_user
				JOIN
					lkp_user_email USING (user_id)
				JOIN
					val_email USING (email_id)
				LEFT JOIN
					val_title ON (val_title.title_id = val_user.user_title_id)
				JOIN
					att_user_password USING (user_id)';

		$result = $old->run($sql);
		$new->add('TRUNCATE user');

		$output= '';
		foreach($result as $row) {

			$new->add('
				INSERT INTO
					user
				(
					user_id,
					created_at,
					created_by,
					updated_by,
					updated_at,
					email,
					password,
					email_confirmed,
					title,
					forename,
					surname,
					last_login_at,
					password_request_at
				)
				VALUES
				(
					:user_id?,
					:created_at?,
					:created_by?,
					:updated_by?,
					:updated_at?,
					:email?,
					:password?,
					:email_confirmed?,
					:title?,
					:forename?,
					:surname?,
					:last_login_at?,
					:password_request_at?
				)', (array) $row);
		}

		$sql ='SELECT
				val_address.address_id,
				user_id,
				\'delivery\' AS type,
				address_name_1 AS line_1,
				address_name_2 AS line_2,
				NULL AS line_3,
				NULL AS line_4,
				address_town AS town,
				postcode AS postcode,
				address_state_id AS state_id,
				country_id,
				telephone_name AS telephone,
				NULL AS created_at,
				NULL AS created_by,
				NULL AS updated_at,
				NULL AS updated_by
			FROM
				val_address
			JOIN
				att_address_postcode USING (address_id)
			JOIN
				lkp_address_country USING (address_id)
			JOIN
				lkp_user_address ON (lkp_user_address.address_id = val_address.address_id AND lkp_user_address.delivery = \'Y\')
			LEFT JOIN
				lkp_user_telephone USING (user_id)
			LEFT JOIN
				val_telephone USING (telephone_id)
			GROUP BY
				address_id';
		$result = $old->run($sql);
		$new->add('TRUNCATE user_address');

		foreach($result as $row) {

			$new->add('
				INSERT INTO
					user_address
				(
					address_id,
					user_id,
					type,
					line_1,
					line_2,
					line_3,
					line_4,
					town,
					postcode,
					state_id,
					country_id,
					telephone,
					created_at,
					created_by,
					updated_at,
					updated_by
				)
				VALUES
				(
					NULL,
					:user_id?,
					:type?,
					:line_1?,
					:line_2?,
					:line_3?,
					:line_4?,
					:town?,
					:postcode?,
					:state_id?,
					:country_id?,
					:telephone?,
					:created_at?,
					:created_by?,
					:updated_at?,
					:updated_by?
				)', (array) $row);
		}

		$sql ='SELECT
				val_address.address_id,
				user_id,
				\'billing\' AS type,
				address_name_1 AS line_1,
				address_name_2 AS line_2,
				NULL AS line_3,
				NULL AS line_4,
				address_town AS town,
				postcode AS postcode,
				address_state_id AS state_id,
				country_id,
				telephone_name AS telephone,
				NULL AS created_at,
				NULL AS created_by,
				NULL AS updated_at,
				NULL AS updated_by
			FROM
				val_address
			JOIN
				att_address_postcode USING (address_id)
			JOIN
				lkp_address_country USING (address_id)
			JOIN
				lkp_user_address ON (lkp_user_address.address_id = val_address.address_id AND lkp_user_address.billing = \'Y\')
			LEFT JOIN
				lkp_user_telephone USING (user_id)
			LEFT JOIN
				val_telephone USING (telephone_id)
			GROUP BY
				address_id';
		$result = $old->run($sql);

		foreach($result as $row) {

			$new->add('
				INSERT INTO
					user_address
				(
					address_id,
					user_id,
					type,
					line_1,
					line_2,
					line_3,
					line_4,
					town,
					postcode,
					state_id,
					country_id,
					telephone,
					created_at,
					created_by,
					updated_at,
					updated_by
				)
				VALUES
				(
					NULL,
					:user_id?,
					:type?,
					:line_1?,
					:line_2?,
					:line_3?,
					:line_4?,
					:town?,
					:postcode?,
					:state_id?,
					:country_id?,
					:telephone?,
					:created_at?,
					:created_by?,
					:updated_at?,
					:updated_by?
				)', (array) $row);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported users</info>');
		}

		return true;
    }
}