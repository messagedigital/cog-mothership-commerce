<?php

namespace Message\Mothership\Commerce\Order;

use Message\Mothership\Commerce\Payment;
use Message\Mothership\Commerce\Shipping;
use Message\Mothership\Commerce\Product\Unit\Unit;
use Message\Mothership\Commerce\Product\Stock\Location\Location as StockLocation;

use Message\User\User;

use Message\Cog\Event\DispatcherInterface;

/**
 * Provides a simpler interface for building an instance of Order.
 *
 * @author Danny Hannah <danny@message.co.uk>
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Assembler
{
	protected $_order;
	protected $_eventDispatcher;
	protected $_defaultStockLocation;

	protected $_dispatchEvents = true;

	protected $_entityTemporaryIdFields = [];

	/**
	 * Constructor.
	 *
	 * @param Order               $order                The order to assemble
	 * @param DispatcherInterface $dispatcher           The event dispatcher
	 * @param string|int          $defaultStockLocation The default stock
	 *                                                  location to use for items
	 */
	public function __construct(Order $order, DispatcherInterface $dispatcher, $defaultStockLocation)
	{
		$this->_order           = $order;
		$this->_eventDispatcher = $dispatcher;

		$this->setDefaultStockLocation($defaultStockLocation);
	}

	/**
	 * Get the order that's being assembled.
	 *
	 * @return Order
	 */
	public function getOrder()
	{
		return $this->_order;
	}

	/**
	 * Replace the order that's being assembled with a new one.
	 *
	 * @param  Order $order The new order
	 *
	 * @return Assembler    Returns $this for chainability
	 */
	public function setOrder(Order $order)
	{
		$this->_order = $order;

		return $this;
	}

	/**
	 * Set the default stock location to set for items added to the order.
	 *
	 * @param  StockLocation $stockLocation
	 *
	 * @return Assembler Returns $this for chainability
	 */
	public function setDefaultStockLocation(StockLocation $stockLocation)
	{
		$this->_defaultStockLocation = $stockLocation;

		return $this;
	}

	/**
	 * Set a property to be used on a specific collection of entities as the ID
	 * property.
	 *
	 * When defined, whenever an entity is passed through this Assembler, the
	 * "id" property value is set to the value of the property defined by this
	 * method.
	 *
	 * For example, if you call `->setEntityTemporaryIdProperty('addresses', 'type');`
	 * and then add an Address entity using `->addEntity('addresses', $address)`,
	 * the `$address` object will have the "id" property value set to the value
	 * of the "type" value.
	 *
	 * @param  string $name     The entity collection name
	 * @param  string $property The name of the property to use
	 *
	 * @return Assembler        Returns $this for chainability
	 */
	public function setEntityTemporaryIdProperty($name, $property)
	{
		$this->_entityTemporaryIdFields[$name] = $property;

		return $this;
	}

	/**
	 * Add an entity to the order. If an entity of the same type with the same
	 * ID exists, it is replaced with the given entity
	 *
	 * If the entity has an `order` property (they all should, really), it is
	 * set to the order that is being assembled.
	 *
	 * The entity is prepared before it is added.
	 *
	 * @see _prepareEntity
	 *
	 * @param  string                 $name   The entity name
	 * @param  Entity\EntityInterface $entity The entity
	 *
	 * @return Assembler                      Returns $this for chainability
	 */
	public function addEntity($name, Entity\EntityInterface $entity)
	{
		$this->_dispatchEvents = false;

		$this->_prepareEntity($name, $entity);

		$this->removeEntity($name, $entity);
		$this->_order->{$name}->append($entity);

		$this->_dispatchEvents = true;

		return $this->dispatchEvent();
	}

	/**
	 * Clear all entities of a certain type, then reset them to a given set of
	 * entities.
	 *
	 * All entities are prepared before being added.
	 *
	 * @see _prepareEntity
	 *
	 * @param  string                         $name     The entity name to set
	 * @param  array[Entity\EntityInterface]  $entities Entities to set
	 *
	 * @return Assembler                                Returns $this for
	 *                                                  chainability
	 */
	public function setEntities($name, array $entities)
	{
		$this->_order->{$name}->clear();

		foreach ($entities as $entity) {
			$this->_prepareEntity($name, $entity);
			$this->_order->{$name}->append($entity);
		}

		return $this->dispatchEvent();
	}

	/**
	 * Remove an entity from the order.
	 *
	 * If an entity object is passed, it is prepared before we try to retrieve
	 * the value of the "id" property.
	 *
	 * @see _prepareEntity
	 *
	 * @param  string                            $name   The entity name
	 * @param  string|int|Entity\EntityInterface $entity The entity, or entity ID
	 *                                                   to remove
	 *
	 * @return Assembler                                 Returns $this for
	 *                                                   chainability
	 */
	public function removeEntity($name, $entity)
	{
		if ($entity instanceof Entity\EntityInterface) {
			$this->_prepareEntity($name, $entity);
		}

		// If we have IDs for these entities, remove using that
		if (array_key_exists($name, $this->_entityTemporaryIdFields)
		 && $entity instanceof Entity\EntityInterface) {
			$this->_order->{$name}->remove($entity->id);
		}
		// Otherwise, remove using the entity instance
		else {
			$this->_order->{$name}->remove($entity);
		}

		return $this->dispatchEvent();
	}

	/**
	 * Clear all entities of a given type.
	 *
	 * @param  string $name The entity name to clear
	 *
	 * @return Assembler    Returns $this for chainability
	 */
	public function clearEntities($name)
	{
		$this->_order->{$name}->clear();

		return $this->dispatchEvent();
	}

	/**
	 * Add a unit to the order.
	 *
	 * The unit is transformed into an Item entity and has the stock location
	 * set to the defined default stock location and is then added to the order.
	 *
	 * @param  Unit $unit The unit to add
	 *
	 * @return Assembler  Returns $this for chainability
	 */
	public function addUnit(Unit $unit)
	{
		$item = new Entity\Item\Item;
		$item->order = $this->_order;

		$item->populate($unit);

		$item->stockLocation = $this->_defaultStockLocation;

		return $this->addItem($item);
	}

	/**
	 * Add an item to the order being assembled.
	 *
	 * @todo Consider removing this method: it is weird that it exists as well
	 *       as addEntity. For the custom validation, maybe allow Assembler to
	 *       have new validation set by a method using a lambda, or just move it
	 *       to listeners on ASSEMBLER_UPDATE event
	 *
	 * @see addEntity
	 *
	 * @param Entity\Item\Item $item The item to add
	 *
	 * @return Assembler             Returns $this for chainability
	 *
	 * @throws \InvalidArgumentException If the item has no valid stock location set
	 */
	public function addItem(Entity\Item\Item $item)
	{
		$item->order = $this->_order;

		if (!($item->stockLocation instanceof StockLocation)) {
			throw new \InvalidArgumentException('Cannot add item to order: must have a valid stock location set');
		}

		$this->addEntity('items', $item);

		return $this->dispatchEvent();
	}

	/**
	 * Update the quantity of items for a given unit on the order being
	 * assembled.
	 *
	 * @param  Unit $unit     The unit to change quantity for
	 * @param  int  $quantity The quantity to set
	 *
	 * @return Assembler      Returns $this for chainability
	 */
	public function setQuantity(Unit $unit, $quantity = 1)
	{
		// Disable event dispatching while we update the quantities
		$this->_dispatchEvents = false;

		// Count how many times this unit is already in the basket
		$row       = $this->_order->items->getRows()[$unit->id];
		$items     = $row->all();
		$unitCount = $row->getQuantity();

		// If the quantities are the same then return
		if ($unitCount == $quantity) {
			return $this;
		}

		// Remove the item as many times needed to make the count equal the given
		// quantity
		if ($quantity < $unitCount) {
			for ($i = $unitCount; $i > $quantity; $i--) {
				$this->_order->items->remove(array_shift($items));
			}
		}

		// Add the item to the order as many times to make the count equal to
		// the given quantity
		if ($quantity > $unitCount) {
			for ($i = $unitCount; $i < $quantity; $i++) {
				if($unitCount === 0) {
					$this->addUnit($unit);
				} else {
					// actual price should be the same on
					// every item
					$this->addItem(clone($row->first()));
				}
			}
		}

		// Re-enable event dispatching now we're done
		$this->_dispatchEvents = true;

		// Dispatch the update event
		return $this->dispatchEvent();
	}

	/**
	 * Update the actual price of items for a given unit on the order being
	 * assembled.
	 *
	 * @param  Unit $unit     The unit to change quantity for
	 * @param  int  $quantity The quantity to set
	 *
	 * @return Assembler      Returns $this for chainability
	 */
	public function setActualPrice(Unit $unit, $actualPrice)
	{
		// Disable event dispatching while we update the quantities
		$this->_dispatchEvents = false;

		$row       = $this->_order->items->getRows()[$unit->id];
		$items     = $row->all();

		// if the actual prices are the same then return
		if ($row->first()->actualPrice === $actualPrice) {
			return $this;
		}

		foreach($items as $item) {
			$item->actualPrice = $actualPrice;
		}

		$this->_dispatchEvents = true;

		return $this->dispatchEvent();
	}

	/**
	 * @see setQuantity
	 * @deprecated Use `setQuantity()` instead. To be removed.
	 */
	public function updateQuantity(Unit $unit, $quantity = 1)
	{
		return $this->setQuantity($unit, $quantity);
	}

	/*
	 * Update the actual price of items for a given unit on the order being
	 * assembled.
	 *
	 * @param  Unit $unit     The unit to change quantity for
	 * @param  int  $quantity The quantity to set
	 *
	 * @return Assembler      Returns $this for chainability
	 */
	public function setActualPrice(Unit $unit, $actualPrice)
	{
		// Disable event dispatching while we update the quantities
		$this->_dispatchEvents = false;

		$row       = $this->_order->items->getRows()[$unit->id];
		$items     = $row->all();

		// if the actual prices are the same then return
		if ($row->first()->actualPrice === $actualPrice) {
			return $this;
		}

		foreach($items as $item) {
			$item->actualPrice = $actualPrice;
		}

		$this->_dispatchEvents = true;

		return $this->dispatchEvent();
	}

	public function addPayment(Payment\MethodInterface $paymentMethod, $amount, $reference)
	{
		$payment            = new Entity\Payment\Payment;
		$payment->method    = $paymentMethod;
		$payment->amount    = $amount;
		$payment->order     = $this->_order;
		$payment->reference = $reference;
		$payment->id 		= $reference;

		foreach ($this->_order->payments->all() as $checkPayment) {
			if ($checkPayment->reference == $payment->reference) {
				return false;
			}
		}

		return $this->addEntity('payments', $payment);
	}

	/**
	 * Add an address to the order. This address will replace any other address
	 * on the order of the same type.
	 *
	 * @param  Entity\Address\Address $address The address to add
	 *
	 * @return Assembler                       Returns $this for chainability
	 */
	public function addAddress(Entity\Address\Address $address)
	{
		if ($this->_order->user) {
			if (is_null($address->forename)) {
				$address->forename = $this->_order->user->forename;
			}

			if (is_null($address->surname)) {
				$address->surname = $this->_order->user->surname;
			}

			if (is_null($address->title)) {
				$address->title = $this->_order->user->title;
			}
		}

		return $this->addEntity('addresses', $address);
	}

	/**
	 * Set the shipping method to use for the order.
	 *
	 * @param Shipping\MethodInterface $option Shipping method to use
	 */
	public function setShipping(Shipping\MethodInterface $option)
	{
		$this->_order->shippingName        = $option->getName();
		$this->_order->shippingDisplayName = $option->getDisplayName();
		$this->_order->shippingListPrice   = $option->getPrice();

		return $this->dispatchEvent();
	}

	/**
	 * Set the user for this order.
	 *
	 * @param  User|null $user The user to set, or null to remove any previously
	 *                         set user
	 *
	 * @return Assembler       Returns $this for chainability
	 */
	public function setUser(User $user = null)
	{
		$this->_order->user = $user;

		return $this->dispatchEvent();
	}

	/**
	 * Remove any user previously set on this order.
	 *
	 * @see setUser
	 *
	 * @return Assembler Returns $this for chainability
	 */
	public function removeUser()
	{
		return $this->setUser(null);
	}

	/**
	 * Dispatch the ASSEMBLER_UPDATE event and set the order to whatever is
	 * returned from the event's `getOrder()` method (this allows listeners to
	 * overwrite the order).
	 *
	 * Note that the event won't dispatch if `$this->_dispatchEvents` is not
	 * true. This allows the assembler to avoid many events being dispatched
	 * when the item quantity is being changed, for example.
	 *
	 * @return Assembler Returns $this for chainability
	 */
	public function dispatchEvent()
	{
		if (true === $this->_dispatchEvents) {
			$this->_order = $this->_eventDispatcher->dispatch(
				Events::ASSEMBLER_UPDATE,
				new Event\AssemblerEvent($this)
			)->getOrder();
		}

		return $this;
	}

	/**
	 * Prepare an entity ready to be added or used for the order.
	 *
	 * This sets the "order" property on the entity (if one is defined) to the
	 * order that's being assembled.
	 *
	 * It also checks if we know of a "temporary id field" for the entity (such
	 * as for addresses and discounts). If one is defined, the value of that
	 * field is set as the value of the "id" property for easy access later.
	 *
	 * @param  string                 $name   The entity collection name
	 * @param  Entity\EntityInterface $entity The entity to prepare
	 *
	 * @return Entity\EntityInterface         The prepared entity
	 */
	protected function _prepareEntity($name, Entity\EntityInterface $entity)
	{
		if (property_exists($entity, 'order')) {
			$entity->order = $this->_order;
		}

		if (array_key_exists($name, $this->_entityTemporaryIdFields)
		 && !$entity->id) {
			$entity->id = $entity->{$this->_entityTemporaryIdFields[$name]};
		}

		return $entity;
	}
}
