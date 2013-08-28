<?php

namespace Message\Mothership\Commerce\Order\Entity;

interface CollectionInterface extends \ArrayAccess, \IteratorAggregate, \Countable
{
	public function get($id);

	public function getByProperty($property, $value);

	public function exists($id);

	public function all();

	public function append(EntityInterface $entity);

	public function remove($id);
}