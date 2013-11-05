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
class Collection implements CollectionInterface
{
	protected $_items = array();

	public function __sleep()
	{
		return array('_items');
	}

	public function get($id)
	{
		foreach ($this->_items as $item) {
			if ($id === $item->id) {
				return $item;
			}
		}

		throw new \InvalidArgumentException(sprintf('Identifier `%s` does not exist on entity collection', $id));
	}

	public function getByProperty($property, $value)
	{
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
		return $this->_items;
	}

	public function append(EntityInterface $entity)
	{
		$this->_items[] = $entity;
	}

	/**
	 * Remove an entity from this collection.
	 *
	 * @param  int|EntityInterface $item Entity ID or exact same entity instance
	 *                                   to be removed (it's not enough to just
	 *                                   all have the same properties)
	 *
	 * @return boolean                   True if the item was removed
	 */
	public function remove($entity)
	{
		foreach ($this->_items as $key => $item) {
			if (($entity instanceof EntityInterface && $entity === $item)
			 || $item->id == $entity) {
				unset($this->_items[$key]);

				return true;
			}
		}

		return false;
	}

	public function clear()
	{
		$this->_items = array();
	}

	public function count()
	{
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
		unset($this->_items[$id]);
	}

	public function getIterator()
	{
		return new \ArrayIterator($this->_items);
	}
}