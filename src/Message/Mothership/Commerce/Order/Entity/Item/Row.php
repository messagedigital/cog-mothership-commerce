<?php

namespace Message\Mothership\Commerce\Order\Entity\Item;

/**
 * Represents an item row, where multiple items for the same unit are displayed
 * as a single row with a quantity.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Row implements \IteratorAggregate, \Countable
{
	protected $_items = array();

	/**
	 * Add an item to this row.
	 *
	 * The item must have the same unit ID as every other item in this row or
	 * an exception will be thrown.
	 *
	 * @param Item $item The item to add
	 *
	 * @throws \InvalidArgumentException If the item's unit ID is different to
	 *                                   the other unit IDs in this row
	 */
	public function add(Item $item)
	{
		if ($this->count() > 0) {
			$firstItem = reset($this->_items);
			if ($item->unitID !== $firstItem->unitID) {
				throw new \InvalidArgumentException(sprintf(
					'Cannot add item to row as it has a different unit ID: `%s` and `%s`',
					$firstItem->unitID,
					$item->unitID
				));
			}
		}

		$this->_items[] = $item;
	}

	/**
	 * Get the first item in this row of items.
	 *
	 * @return Item
	 */
	public function first()
	{
		return reset($this->_items);
	}

	/**
	 * Get the sum of the values of a particular property or the return values
	 * of a particular method on all the items in this row.
	 *
	 * @param  string $name     The property/method name to sum
	 *
	 * @return float|int        The sum of the values in this row
	 *
	 * @throws \BadMethodCallException   If no items have been set on this row yet
	 * @throws \InvalidArgumentException If neither a property nor a method of
	 *                                   that name exists on any of the items
	 */
	public function sum($name)
	{
		if ($this->count() < 1) {
			throw new \BadMethodCallException(sprintf(
				'Cannot sum `%s` property for item row: no items have been set',
				$property
			));
		}

		$return = 0;

		foreach ($this->_items as $item) {
			if (property_exists($item, $name)) {
				$value = $item->{$name};
			}
			else if (method_exists($item, $name)) {
				$value = $item->{$name}();
			}
			else {
				throw new \InvalidArgumentException(sprintf(
					'Cannot sum `%s` property/method for item row: neither property nor method of that name exists',
					$name
				));
			}

			$return += $value;
		}

		return $return;
	}

	/**
	 * Collapse the values of a particular property or the return values of a
	 * particular method on all the items in this row into a comma-separated
	 * string.
	 *
	 * Duplicates are removed unless the second argument is passed as `true`.
	 *
	 * @param  string  $name            The property/method name to collapse
	 * @param  boolean $allowDuplicates True to allow duplicate values, false
	 *                                  otherwise
	 *
	 * @return string                   The values collapsed as a string
	 *
	 * @throws \BadMethodCallException   If no items have been set on this row yet
	 * @throws \InvalidArgumentException If neither a property nor a method of
	 *                                   that name exists on any of the items
	 */
	public function collapse($name, $allowDuplicates = false)
	{
		if ($this->count() < 1) {
			throw new \BadMethodCallException(sprintf(
				'Cannot collapse `%s` property/method for item row: no items have been set',
				$name
			));
		}

		$return = array();

		foreach ($this->_items as $item) {
			if (property_exists($item, $name)) {
				$value = $item->{$name};
			}
			else if (method_exists($item, $name)) {
				$value = $item->{$name}();
			}
			else {
				throw new \InvalidArgumentException(sprintf(
					'Cannot collapse `%s` property/method for item row: neither property nor method of that name exists',
					$name
				));
			}

			// Only add the value if it's unique or duplicates are allowed
			if ($allowDuplicates || !in_array($value, $return)) {
				$return[] = $value;
			}
		}

		return implode(', ', $return);
	}

	/**
	 * @see count
	 */
	public function getQuantity()
	{
		return $this->count();
	}

	/**
	 * Get the number of items in this row.
	 *
	 * @return int
	 */
	public function count()
	{
		return count($this->_items);
	}

	/**
	 * Get the iterator to use when iterating over this class.
	 *
	 * @return \ArrayIterator Iterator for the items in this row
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->_items);
	}
}