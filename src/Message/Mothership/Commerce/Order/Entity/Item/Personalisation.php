<?php

namespace Message\Mothership\Commerce\Order\Entity\Item;

/**
 * Represents personalisation data for an order item.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Personalisation implements \IteratorAggregate, \Countable, \ArrayAccess
{
	protected $_data = array();

	static public function getDisplayName($name)
	{
		return ucfirst(str_replace(array('_', '-'), ' ', $name));
	}

	public function __get($name)
	{
		return $this->get($name);
	}

	public function __set($name, $value)
	{
		return $this->set($name, $value);
	}

	public function __isset($name)
	{
		return $this->exists($name);
	}

	public function __unset($name)
	{
		return $this->remove($name);
	}

	public function __toString()
	{
		$return = '';

		foreach ($this->_data as $name => $value) {
			$return .= statis::getDisplayName($name) . ': ' . $value . "\n";
		}

		return $return;
	}

	public function offsetGet($name)
	{
		return $this->get($name);
	}

	public function offsetSet($name, $value)
	{
		return $this->set($name, $value);
	}

	public function offsetExists($name)
	{
		return $this->exists($name);
	}

	public function offsetUnset($name)
	{
		return $this->remove($name);
	}

	public function get($name)
	{
		if (!$this->exists($name)) {
			return null;
		}

		return $this->_data[$name];
	}

	public function set($name, $value)
	{
		$this->_data[$name] = $value;
	}

	public function exists($name)
	{
		return array_key_exists($name, $this->_data);
	}

	public function remove($name)
	{
		if (!$this->exists($name)) {
			throw new \InvalidArgumentException(sprintf(
				'Could not remove item personalisation `%s` as it is not set.',
				$name
			));
		}

		unset($this->_data[$name]);
	}

	public function all()
	{
		return $this->_data;
	}

	public function count()
	{
		return count($this->_data);
	}

	public function getIterator()
	{
		return new \ArrayIterator($this->_data);
	}
}