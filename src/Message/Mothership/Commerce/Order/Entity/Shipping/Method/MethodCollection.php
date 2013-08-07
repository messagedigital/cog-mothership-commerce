<?php

namespace Message\Mothership\Commerce\Order\Entity\Shipping\Method;

use Message\Mothership\Commerce\Order\Order;

/**
 * A container for all shipping methods available to the system.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class MethodCollection implements \IteratorAggregate, \Countable
{
	protected $_methods = array();

	/**
	 * Constructor.
	 *
	 * @param array $methods An array of shipping methods
	 */
	public function __construct(array $methods = array())
	{
		foreach ($methods as $name => $method) {
			$this->add($method);
		}
	}

	/**
	 * Add a shipping method to this collection.
	 *
	 * @param MethodInterface $method   The shipping method to add
	 *
	 * @return MethodCollection         Returns $this for chainability
	 *
	 * @throws \InvalidArgumentException  If a shipping method with the same name
	 *                                    has already been set on this collection
	 */
	public function add(MethodInterface $method)
	{
		if (isset($this->methods[$method->getName()])) {
			throw new \InvalidArgumentException(sprintf('Shipping method `%s` is already defined', $method->getName()));
		}

		$this->_methods[$method->getName()] = $method;

		return $this;
	}

	public function getForOrder(Order $order)
	{
		if(!$order) {
			//return array();
		}

		$availableMethods = array();

		foreach($this->getIterator() as $name => $method) {
			if($method->isAvailable($order)) {
				$availableMethods[$name] = $method;
			}
		}

		return $availableMethods;
	}

	/**
	 * Get a shipping method set on this collection by name.
	 *
	 * @param  string $name    The shipping method name
	 *
	 * @return MethodInterface The shipping method instance
	 *
	 * @throws \InvalidArgumentException If the shipping method has not been set
	 */
	public function get($name)
	{
		if (!isset($this->_methods[$name])) {
			throw new \InvalidArgumentException(sprintf('Shipping method `%s` not set on collection', $name));
		}

		return $this->_methods[$name];
	}

	/**
	 * Get the number of shipping methods registered on this collection.
	 *
	 * @return int The number of shipping methods registered
	 */
	public function count()
	{
		return count($this->_methods);
	}

	/**
	 * Get the iterator object to use for iterating over this class.
	 *
	 * @return \ArrayIterator An \ArrayIterator instance for the `_methods`
	 *                        property
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->_methods);
	}
}