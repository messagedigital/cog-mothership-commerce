<?php

namespace Message\Mothership\Commerce\Order\Basket;

class Token
{
	public function __construct($hash, $config)
	{
		$this->_hash = $hash;
		$this->_config = $config;
	}

	/**
	 * Generate a token for the basket
	 *
	 * @param $basketID
	 * @param $createdAt
	 * @return string
	 */
	public function generate($basketID, $createdAt)
	{
		$pepper = $this->_config->basket->pepper;

		$data = join('-', array($basketID, $createdAt));

		return $basketID . "-" . $this->_hash->encrypt($data, $pepper);
	}

	/**
	 * Validate the basket token against the cookie hash
	 *
	 * @param $basketID
	 * @param $updatedAt
	 * @param $hash
	 * @return bool
	 */
	public function validate($basketID, $createdAt, $hash)
	{
		$token = $this->generate($basketID, $createdAt);
		return ($token == $hash);
	}
}