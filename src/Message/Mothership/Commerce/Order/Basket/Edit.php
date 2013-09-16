<?php

namespace Message\Mothership\Commerce\Order\Basket;

use Message\Cog\DB;
use Message\Mothership\Commerce\Order\Order;

class Edit implements DB\TransactionalInterface {

	protected $_query;

	public function __construct(DB\Query $query)
	{
		$this->_query = $query;
	}

	public function setTransaction(DB\Transaction $trans)
	{
		$this->_query = $trans;
	}

	public function update($basketID, Order $order)
	{
		$time = time();

		$userID = ($order->user) ? $order->user->id : NULL;

		$data['userID'] 	= $userID;
		$data['contents'] 	= serialize($order);
		$data['updatedAt'] 	= $time;

		$data['basketID']	= $basketID;

		$result = $this->_query->run('
			UPDATE
				basket
			SET
				user_id  	= :userID?i,
				contents 	= :contents?s,
				updated_at 	= :updatedAt?i
			WHERE
				basket_id	= :basketID?s
		', $data);

		return $result->affected();
	}
}