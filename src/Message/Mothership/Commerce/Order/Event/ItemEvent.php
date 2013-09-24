<?php

namespace Message\Mothership\Commerce\Order\Event;

use Message\Mothership\Commerce\Order\Order;
use Message\Mothership\Commerce\Order\Entity\Item\Item;

/**
 * Item event that allows you to set / get the item in addition to the order.
 *
 * @author Laurence Roberts
 */
class ItemEvent extends Event
{
	protected $_item;

	/**
	 * Constructor.
	 * 
	 * @param Order $order
	 * @param Item  $item
	 */
	public function __construct(Order $order, Item $item)
	{
		parent::__construct($order);
		$this->setItem($item);
	}

	/**
	 * Get the item.
	 * 
	 * @return Item
	 */
	public function getItem()
	{
		return $this->_item;
	}

	/**
	 * Set the item.
	 * 
	 * @param Item $item
	 */
	public function setItem(Item $item)
	{
		$this->_item = $item;
	}
}