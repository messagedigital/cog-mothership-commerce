<?php

namespace Message\Mothership\Commerce\Order\Basket;

use Message\Cog\DB;
use Message\Mothership\Commerce\Order\Order;

class Create implements DB\TransactionalInterface {

	protected $_query;

	public function __construct(DB\Query $query)
	{
		$this->_query = $query;
	}

	public function setTransaction(DB\Transaction $trans)
	{
		$this->_query = $trans;
	}

	public function create(Order $order)
	{
		$time = time();

		$userID = ($order->user) ? $order->user->id : NULL;

		$data['userID'] 	= $userID;
		$data['contents'] 	= serialize($order);
		$data['createdAt'] 	= $time;
		$data['updatedAt'] 	= $time;

		$result = $this->_query->run('
			INSERT INTO
				basket
			SET
				user_id 	= :userID?i,
				contents 	= :contents?s,
				created_at 	= :createdAt?i,
				updated_at  = :updatedAt?i
		', $data);

		$basketID = $result->id();

		return $basketID;
	}
}