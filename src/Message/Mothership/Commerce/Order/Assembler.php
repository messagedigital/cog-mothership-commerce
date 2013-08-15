<?php

namespace Message\Mothership\Commerce\Order;

use Message\Mothership\Commerce\User\LoaderInterface;
use Message\Mothership\Commerce\Shipping\MethodInterface as ShippingInterface;
use Message\Mothership\Commerce\Product\Unit\Unit;
use Message\User\UserInterface;
use Message\Cog\Localisation\Locale;
use Message\Mothership\Commerce\Order\Entity\Item\Item;
use Message\Cog\Event\DispatcherInterface;
use Message\Cog\HTTP\Session;

use Message\Mothership\Commerce\Order\Event\Event;
use Message\Mothership\Commerce\Order\Events;

/**
 *
 *
 * @author Danny Hannah <danny@message.co.uk>
 */
class Assembler
{

	protected $_user;
	protected $_locale;
	protected $_order;
	protected $_eventDispatcher;
	protected $_session;

	public function __construct(Order $order, UserInterface $user, Locale $locale, DispatcherInterface $event,Session $session)
	{
		$this->_order           = $order;
		$this->_order->currencyID = 'GBP';
		$this->_order->type = 'web';
		$this->_user            = $user;
		$this->_locale          = $locale;
		$this->_eventDispatcher = $event;
		$this->_session			= $session;
	}

	public function addItem(Unit $unit)
	{
		$item = new Entity\Item\Item;
		$item->order = $this->_order;

		$this->_order->items->append($item->populate($unit));

		$event = new Event($this->_order);
		// Dispatch the edit event

		$this->_eventDispatcher->dispatch(
			Events::ASSEMBLER_UPDATE,
			$event
		);

		return $this;
	}

	public function removeItem(Item $item)
	{
		$this->_order->items->remove($item->id);

		$event = new Event($this->_order);
		// Dispatch the edit event

		$this->_eventDispatcher->dispatch(
			Events::ASSEMBLER_UPDATE,
			$event
		);

		return $this;
	}

	public function updateQuantity(Unit $unit, $quantity = 1)
	{
		// Count how many times this unit is already in the basket
		$unitCount = $this->_countForUnitID($unit);
		// Load the itesm from the basket which already have this unitID
		$items = $this->_order->items->getByProperty('unitID', $unit->id);

		// If the quantities are the same then return
		if ($unitCount == $quantity) {
			return $this;
		}

		// Remove the item as many times needed to make the count equal the given
		// quantity
		if ($quantity < $unitCount) {
			for ($i = $unitCount ; $i > $quantity; $i--) {
				$this->removeItem(array_shift($items));
			}
		}

		// Add the item to the order as many times to make the count equal to
		// the given quantity
		if ($quantity > $unitCount) {
			for ($i = $unitCount; $i < $quantity; $i++) {
				$this->addItem($unit);
			}
		}

		return $this;
	}

	public function setOrder(Order $order)
	{
		$this->_order = $order;
	}

	public function getItemQuantity(Unit $unit)
	{
		return $this->_countForUnitID($unit);
	}

	public function emptyBasket()
	{
		$this->_session->remove('basket.order');

		return true;
	}

	public function addVoucher()
	{

	}

	public function addUser(\Message\User\User  $user)
	{
		$this->_order->user = $user;

		return $this;
	}

	public function removeVoucher()
	{

	}

	public function addDiscount()
	{

	}

	public function removeDiscount()
	{

	}

	public function hasAddress()
	{

	}

	public function addAddress(Entity\Address\Address $address)
	{
//		de(xdebug_print_function_stack());
		if (is_null($address->forename)) {
			$address->forename = $this->_user->forename;
		}

		if (is_null($address->surname)) {
			$address->surname = $this->_user->surname;

		}

		if (is_null($address->title)) {
			$address->title = $this->_user->title;
		}

		$address->authorship = new \Message\Cog\ValueObject\Authorship;
		// ID is set as the type so this will remove all the address types from the
		// basket so we only have one billing and one delivery address
		$this->_order->addresses->remove($address->id);

		return $this->_order->addresses->append($address);
	}

	public function setShipping(ShippingInterface $option)
	{
		$this->_order->shippingName      = $option->getName();
		$this->_order->shippingListPrice = $option->getPrice();

		return $this;
	}

	public function getOrder()
	{
		return $this->_order;
	}

	protected function _countForUnitID(Unit $unit)
	{
		return count($this->_order->items->getByProperty('unitID', $unit->id));
	}

}