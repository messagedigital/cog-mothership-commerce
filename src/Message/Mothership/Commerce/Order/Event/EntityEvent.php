<?php

namespace Message\Mothership\Commerce\Order\Event;

use Message\Mothership\Commerce\Order\Order;
use Message\Mothership\Commerce\Order\Entity\EntityInterface;

/**
 * Event for something specific to an entity.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class EntityEvent extends TransactionalEvent
{
	protected $_entity;

	/**
	 * Constructor.
	 *
	 * @param Order           $order
	 * @param EntityInterface $entity
	 */
	public function __construct(Order $order, EntityInterface $entity)
	{
		parent::__construct($order);

		$this->setEntity($entity);
	}

	/**
	 * Get the entity.
	 *
	 * @return EntityInterface
	 */
	public function getEntity()
	{
		return $this->_entity;
	}

	/**
	 * Set the entity.
	 *
	 * @param EntityInterface $entity
	 */
	public function setEntity(EntityInterface $entity)
	{
		$this->_entity = $entity;
	}
}