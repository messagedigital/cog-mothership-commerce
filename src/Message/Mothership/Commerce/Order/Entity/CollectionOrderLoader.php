<?php

namespace Message\Mothership\Commerce\Order\Entity;

use Message\Mothership\Commerce\Order\Order;

/**
 * Proxy / facade for a collection of entities for a specific order.
 *
 * The purpose of this class is to load the entities for a specific order when
 * the collection methods are first accessed. This prevents entities from being
 * loaded if they aren't being accessed.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class CollectionOrderLoader implements CollectionInterface
{
	protected $_collection;
	protected $_loader;
	protected $_order;

	protected $_loaded = false;

	public function __construct(Collection $collection, LoaderInterface $loader)
	{
		$this->_collection = $collection;
		$this->_loader     = $loader;
	}

	public function __sleep()
	{
		return array('_collection', '_loaded', '_order');
	}

	public function __call($method, $args)
	{
		if (method_exists($this->_collection, $method)) {
			$this->load();

			return call_user_func_array(array($this->_collection, $method), $args);
		}

		throw new \BadMethodCallException(sprintf('Method `%s` does not exist on `%s`', $method, get_class($this->_collection)));
	}

	public function setOrder(Order $order)
	{
		if ($this->_order !== $order) {
			$this->_loaded = false;
		}

		$this->_order = $order;
	}

	public function getLoader()
	{
		return $this->_loader;
	}

	public function get($id)
	{
		$this->load();

		return $this->_collection->get($id);
	}

	public function getByProperty($property, $value)
	{
		$this->load();

		return $this->_collection->getByProperty($property, $value);
	}

	public function exists($id)
	{
		$this->load();

		return $this->_collection->exists($id);
	}

	public function all()
	{
		$this->load();

		return $this->_collection->all();
	}

	public function append(EntityInterface $entity)
	{
		if ($this->_order && property_exists($entity, 'order') && !$entity->order) {
			$entity->order = $this->_order;
		}

		return $this->_collection->append($entity);
	}

	public function remove($id)
	{
		$this->load();

		return $this->_collection->remove($id);
	}

	public function count()
	{
		$this->load();

		return $this->_collection->count();
	}

	public function offsetSet($id, $value)
	{
		throw new \BadMethodCallException('`Entity\Collection` does not allow setting entities using array access');
	}

	public function offsetGet($id)
	{
		$this->load();

		return $this->_collection->offsetGet($id);
	}

	public function offsetExists($id)
	{
		$this->load();

		return $this->_collection->offsetExists($id);
	}

	public function offsetUnset($id)
	{
		$this->load();

		return $this->_collection->offsetUnset($id);
	}

	public function getIterator()
	{
		$this->load();

		return $this->_collection->getIterator();
	}

	public function load()
	{
		if (!$this->_loaded) {
			if (!$this->_order) {
				throw new \LogicException('Cannot load entity collection as no order has been set yet');
			}

			if ($this->_order->id && is_int($this->_order->id) && $items = $this->_loader->getByOrder($this->_order)) {
				foreach ($items as $item) {
					$this->_collection->append($item);
				}
			}

			return $this->_loaded = true;
		}

		return false;
	}
}