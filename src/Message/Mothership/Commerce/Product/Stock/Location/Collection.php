<?php

namespace Message\Mothership\Commerce\Product\Stock\Location;

/**
 * A container for stock locations that are available throughout the system.
 */
class Collection implements \IteratorAggregate, \Countable
{
	protected $_locations = array();
	protected $_roles = array();

	const SELL_ROLE = 'sell';
	const HOLD_ROLE = 'hold';
	const BIN_ROLE  = 'bin';

	/**
	 * Constructor.
	 *
	 * @param array $locations An array of locations to add
	 */
	public function __construct(array $locations = array())
	{
		foreach ($locations as $location) {
			$this->add($location);
		}
	}

	/**
	 * Add a location to this collection.
	 *
	 *
	 * @param Location 						$location The location to add
	 *
	 * @return Collection 					Returns $this for chainability
	 *
	 * @throws \InvalidArgumentException 	If the location has no name set
	 * @throws \InvalidArgumentException 	If the location has no displayName set
	 * @throws \InvalidArgumentException 	If a location with the same name has
	 *                                   	already been set on this collection
	 */
	public function add(Location $location)
	{
		if (!$location->name) {
			throw new \InvalidArgumentException(sprintf('Location `%s` has no name', $location->displayName));
		}

		if (!$location->displayName) {
			throw new \InvalidArgumentException(sprintf('Location `%s` has no display-name', $location->name));
		}

		if ($this->exists($location->name)) {
			throw new \InvalidArgumentException(sprintf(
				'Location with name `%s` is already defined as `%s`',
				$location->name,
				$this->_location[$location->name]->displayName
			));
		}

		$this->_locations[$location->name] = $location;

		return $this;
	}

	/**
	 * Get a location set on this collection by the name.
	 *
	 * @param  string $name 				The location's name
	 *
	 * @return GroupInterface 				The group instance
	 *
	 * @throws \InvalidArgumentException 	If the location has not been set
	 */
	public function get($name)
	{
		if (!$this->exists($name)) {
			throw new \InvalidArgumentException(sprintf('Location with name `%s` not set on collection', $name));
		}

		return $this->_locations[$name];
	}

	/**
	 * Get all locations set on this collection, where the keys are the location
	 * names.
	 *
	 * @return array
	 */
	public function all()
	{
		return $this->_locations;
	}

	/**
	 * Check if a given location name has been defined on this collection.
	 *
	 * @param  string $name 	The location's name
	 *
	 * @return boolean 			True if it exists, false otherwise
	 */
	public function exists($name)
	{
		return array_key_exists($name, $this->_locations);
	}

	/**
	 * Get the number of locations registered on this collection.
	 *
	 * @return int The number of locations registered
	 */
	public function count()
	{
		return count($this->_locations);
	}

	/**
	 * Set the location for a role.
	 * 
	 * @param string $role
	 * @param string $name
	 *
	 * @return Collection
	 */
	public function setRoleLocation($role, $name)
	{
		$this->_roles[$role] = $name;

		return $this;
	}

	/**
	 * Get the location for a role.
	 * 
	 * @param  string $role
	 * 
	 * @return Location
	 */
	public function getRoleLocation($role)
	{
		if (! array_key_exists($this->_roles, $role)) {
			throw new \InvalidArgumentException(sprintf('Location role `%s` does not exist', $role));
		}

		return $this->get($this->_roles[$role]);
	}

	/**
	 * Get the iterator object to use for iterating over this class.
	 *
	 * @return \ArrayIterator An \ArrayIterator instance for the `_locations`
	 *                        property
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->_locations);
	}
}