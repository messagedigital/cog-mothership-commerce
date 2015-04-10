<?php

namespace Message\Mothership\Commerce\Order\Entity;

use Message\Cog\ValueObject\Collection as BaseCollection;

/**
 * Collection of entities relating to a specific order.
 *
 * Entities are lazy loaded on demand.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 * @author Thomas Marchant <thomas@message.co.uk>
 */
class Collection extends BaseCollection implements CollectionInterface
{
	protected $_items = array();

	public function __sleep()
	{
		return array('_items');
	}

	/**
	 * Add validation and set key to the ID
	 */
	protected function _configure()
	{
		$this->addValidator(function ($item) {
			if (!$item instanceof EntityInterface) {
				throw new \InvalidArgumentException('Item must be an instance of EntityInterface');
			}
		});

		$this->setKey(function ($item) {
			if (property_exists($item, 'id')) {
				return $item->id;
			}
			if (method_exists($item, 'getID')) {
				return $item->getID();
			}
			if (method_exists($item, 'getId')) {
				return $item->getId();
			}

			return count($this->all()) - 1;
		});
	}

	/**
	 * @param mixed $id
	 * @throws \InvalidArgumentException
	 *
	 * @return EntityInterface
	 */
	public function get($id)
	{
		foreach ($this->_items as $item) {
			if ($id == $item->id) {
				return $item;
			}
		}

		throw new \InvalidArgumentException(sprintf('Identifier `%s` does not exist on entity collection', $id));
	}

	/**
	 * @param $property
	 * @param $value
	 *
	 * @return array
	 */
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

	/**
	 * Preserved for backwards compatibility. Use `add()` instead.
	 *
	 * @param EntityInterface $entity
	 */
	public function append(EntityInterface $entity)
	{
		$this->add($entity);
	}

	/**
	 * Remove an entity from this collection.
	 *
	 * @param  int|EntityInterface $entity  Entity ID or exact same entity instance
	 *                                      to be removed (it's not enough to just
	 *                                      all have the same properties)
	 *
	 * @return boolean                      True if the item was removed
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
}