<?php

namespace Message\Mothership\Commerce\Order\Event;

use Message\Mothership\Commerce\Order\Order;
use Message\Mothership\Commerce\Order\Entity\Dispatch\Dispatch;

/**
 * Dispatch event that allows you to set / get the dispatch in addition to the order.
 *
 * @author Laurence Roberts
 */
class DispatchEvent extends Event
{
	protected $_dispatch;

	/**
	 * Constructor.
	 *
	 * @param Order $order
	 * @param Dispatch  $dispatch
	 */
	public function __construct(Dispatch $dispatch, Order $order = null)
	{
		$order = $order ?: $dispatch->order;
		parent::__construct($order);
		$this->setDispatch($dispatch);
	}

	/**
	 * Get the dispatch.
	 *
	 * @return Dispatch
	 */
	public function getDispatch()
	{
		return $this->_dispatch;
	}

	/**
	 * Set the dispatch.
	 *
	 * @param Dispatch $dispatch
	 */
	public function setDispatch(Dispatch $dispatch)
	{
		$this->_dispatch = $dispatch;
	}
}