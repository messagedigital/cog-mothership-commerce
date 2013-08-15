<?php

namespace Message\Mothership\Commerce\Order\Entity;

use Message\Mothership\Commerce\Order\Order;

/**
 * Collection of entities relating to a specific order.
 *
 * Entities are lazy loaded on demand.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Collection implements \ArrayAccess, \IteratorAggregate, \Countable
{
	protected $_order;
	protected $_loader;

	protected $_loaded = false;
	protected $_items  = array();

	public function __construct(Order $order, LoaderInterface $loader)
	{
		$this->_order  = $order;
		$this->_loader = $loader;
	}

	public function __sleep()
	{
		return array('_items', '_loaded', '_order');
	}

	public function get($id)
	{
		$this->load();

		foreach ($this->_items as $item) {
			if ($id === $item->id) {
				return $item;
			}
		}

		throw new \InvalidArgumentException(sprintf('Identifier `%s` does not exist on entity collection', $id));
	}

	public function getByProperty($property, $value)
	{
		$this->load();

		$return = array();

		foreach ($this->_items as $id => $item) {
			if (property_exists($item, $property) && $item->{$property} == $value) {
				$return[$id] = $item;
			}
		}

		return $return;
	}

	public function exists($id)
	{
		$this->load();

		try {
			$this->get($id);
		}
		catch (\InvalidArgumentException $e) {
			return false;
		}

		return true;
	}

	public function all()
	{
		$this->load();

		return $this->_items;
	}

	public function append(EntityInterface $entity)
	{
		if (property_exists($entity, 'order') && !$entity->order) {
			$entity->order = $this->_order;
		}

		$this->_items[] = $entity;
	}

	public function remove($id)
	{
		foreach ($this->_items as $key => $item) {
			if ($item->id == $id) {
				unset($this->_items[$key]);
				return true;
			}
		}

		return false;
	}

	public function count()
	{
		$this->load();

		return count($this->_items);
	}

	public function offsetSet($id, $value)
	{
		throw new \BadMethodCallException('`Entity\Collection` does not allow setting entities using array access');
	}

	public function offsetGet($id)
	{
		return $this->get($id);
	}

	public function offsetExists($id)
	{
		return $this->exists($id);
	}

	public function offsetUnset($id)
	{
		$this->load();

		unset($this->_items[$id]);
	}

	public function getIterator()
	{
		$this->load();

		return new \ArrayIterator($this->_items);
	}

	public function load()
	{
		if (!$this->_loaded) {
			if ($this->_order->id && is_int($this->_order->id) && $items = $this->_loader->getByOrder($this->_order)) {
				foreach ($items as $item) {
					$this->append($item);
				}
			}

			return $this->_loaded = true;
		}

		return false;
	}
}