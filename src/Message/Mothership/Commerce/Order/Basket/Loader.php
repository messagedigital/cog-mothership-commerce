<?php

namespace Message\Mothership\Commerce\Order\Basket;

use Message\Cog\DB;
use Message\Mothership\Commerce\Order\Basket\Token;

class Loader
{
	protected $_query;
	protected $_token;

	public function __construct(DB\Query $query, Token $token)
	{
		$this->_query = $query;
		$this->_token = $token;
	}

	/**
	 * Get the basket by ID.
	 *
	 * @param $basketID
	 * @return mixed
	 */
	public function getByID($basketID)
	{
		$data['basketID'] = $basketID;

		$result = $this->_query->run('
			SELECT
				basket_id,
				user_id,
				contents,
				created_at,
				updated_at
			FROM
				basket
			WHERE
				basket_id = :basketID?i
			LIMIT 1
		', $data);

		$data = $result->first();

		return $data;
	}

	/**
	 * Get the basket by token.
	 *
	 * @param $token
	 * @return mixed|string
	 */
	public function getByToken($token)
	{
		// Extract the ID for the basket from the hash
		list($basketID) = explode('-', $token, 2);

		$result = $this->_query->run('
			SELECT
				basket_id,
				user_id,
				contents,
				created_at,
				updated_at
			FROM
				basket
			WHERE
				basket_id = :basketID?s
			ORDER BY
				updated_at DESC
			LIMIT 1
		', array('basketID' => $basketID));

		$data = $result->first();

		if(!$data) {
			return false;
		}

		// Validate basket is for the token
		if(!$this->_token->validate($data->basket_id, $data->created_at, $token)) {
			return false;
		}

		return $data;
	}

	/**
	 * Unserialize the contents of the basket into an Order object.
	 *
	 * @param $data
	 * @return mixed|string
	 */
	public function order($data)
	{
		return ($data) ? unserialize($data->contents) : '';
	}
}