<?php

namespace Message\Mothership\Commerce\Order\Entity\Dispatch;

use Message\User\UserInterface;

use Message\Cog\DB;
use Message\Cog\Event\DispatcherInterface;
use Message\Cog\ValueObject\DateTimeImmutable;

use Message\Mothership\Commerce\Order;

class Edit implements DB\TransactionalInterface
{
	protected $_query;
	protected $_currentUser;
	protected $_eventDispatcher;

	public function __construct(DB\Query $query, UserInterface $currentUser, DispatcherInterface $eventDispatcher)
	{
		$this->_query           = $query;
		$this->_currentUser     = $currentUser;
		$this->_eventDispatcher = $eventDispatcher;
	}

	public function setTransaction(DB\Transaction $trans)
	{
		$this->_query = $trans;
	}

	public function ship(Dispatch $dispatch)
	{
		if ($dispatch->shippedAt || $dispatch->shippedBy) {
			throw new \InvalidArgumentException(sprintf('Dispatch #%s cannot be shipped: it is already shipped', $dispatch->id));
		}

		$dispatch->shippedAt = new DateTimeImmutable;
		$dispatch->shippedBy = $this->_currentUser->id;

		$this->_query->run('
			UPDATE
				order_dispatch
			SET
				shipped_at = :shippedAt?d,
				shipped_by = :shippedBy?in
			WHERE
				dispatch_id = :id?i
		', array(
			'shippedAt' => $dispatch->shippedAt,
			'shippedBy' => $dispatch->shippedBy,
			'id'        => $dispatch->id,
		));

		$event = new Order\Event\DispatchEvent($dispatch);
		$this->_eventDispatcher->dispatch(Order\Events::DISPATCH_SHIPPED, $event);

		return $dispatch;
	}

	public function postage(Dispatch $dispatch, $code, $cost = null)
	{
		if ($dispatch->code) {
			throw new \InvalidArgumentException(sprintf('Dispatch #%s cannot be postaged: it already has a code', $dispatch->id));
		}

		if ($dispatch->cost) {
			throw new \InvalidArgumentException(sprintf('Dispatch #%s cannot be postaged: it already has a cost', $dispatch->id));
		}

		$dispatch->code = $code;

		if (!is_null($cost)) {
			$dispatch->cost = (float) $cost;
		}

		$this->_query->run('
			UPDATE
				order_dispatch
			SET
				code       = :code?s,
				cost       = :cost?fn,
				address_id = :addressID?i
			WHERE
				dispatch_id = :id?i
		', array(
			'code'       => $dispatch->code,
			'cost'       => $dispatch->cost,
			'addressID'  => $dispatch->order->getAddress('delivery')->id,
			'id'         => $dispatch->id,
		));

		return $dispatch;
	}
}