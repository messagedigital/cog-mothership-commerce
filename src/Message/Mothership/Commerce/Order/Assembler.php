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
use Message\Mothership\Commerce\Order\Entity\Payment\MethodInterface;

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
		$this->_order             = $order;
		$this->_order->currencyID = 'GBP';
		$this->_order->type       = 'web';
		$this->_user              = $user;
		$this->_locale            = $locale;
		$this->_eventDispatcher   = $event;
		$this->_session           = $session;
	}

	public function addItem(Unit $unit, $stockLocation)
	{
		$item = new Entity\Item\Item;
		$item->order = $this->_order;
		$item->stockLocation = $stockLocation;

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
		// Load the items from the basket which already have this unitID
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
			$item = array_shift($items);
			for ($i = $unitCount; $i < $quantity; $i++) {
				$this->addItem($unit, $item->stockLocation);
			}
		}

		$event = new Event($this->_order);
		// Dispatch the edit event
		$this->_eventDispatcher->dispatch(
			Events::ASSEMBLER_UPDATE,
			$event
		);

		return $this;
	}

	public function setOrder(Order $order)
	{
		$this->_order = $order;

		$event = new Event($this->_order);
		// Dispatch the edit event
		$this->_eventDispatcher->dispatch(
			Events::ASSEMBLER_UPDATE,
			$event
		);
	}

	public function getItemQuantity(Unit $unit)
	{
		return $this->_countForUnitID($unit);
	}

	public function emptyBasket()
	{
		$this->_session->remove('basket.order');

		$event = new Event($this->_order);
		// Dispatch the edit event
		$this->_eventDispatcher->dispatch(
			\Message\Mothership\Ecommerce\Event::EMPTY_BASKET,
			$event
		);

		return true;
	}

	public function addUser(\Message\User\UserInterface  $user)
	{
		$this->_order->user = $user;

		$event = new Event($this->_order);
		// Dispatch the edit event
		$this->_eventDispatcher->dispatch(
			Events::ASSEMBLER_UPDATE,
			$event
		);

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

	public function addPayment(MethodInterface $paymentMethod, $amount, $reference, $silenceEvent = false)
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

		$this->_order->payments->append($payment);

		if (!$silenceEvent) {
			$event = new Event($this->_order);
			// Dispatch the edit event
			$this->_eventDispatcher->dispatch(
				Events::ASSEMBLER_UPDATE,
				$event
			);
		}

		return $this;

	}

	public function addAddress(Entity\Address\Address $address)
	{
		if (is_null($address->forename)) {
			$address->forename = $this->_user->forename;
		}

		if (is_null($address->surname)) {
			$address->surname = $this->_user->surname;

		}

		if (is_null($address->title)) {
			$address->title = $this->_user->title;
		}

		// ID is set as the type so this will remove all the address types from the
		// basket so we only have one billing and one delivery address
		$this->_order->addresses->remove($address->id);

		$this->_order->addresses->append($address);

		$event = new Event($this->_order);
		// Dispatch the edit event
		$this->_eventDispatcher->dispatch(
			Events::ASSEMBLER_ADDRESS_UPDATE,
			$event
		);

		return $this;
	}

	/**
	 * This is used when the user logs out and we need to clear all the addresses
	 * from the basket as a log out doesn't empty the basket.
	 *
	 * @return $this
	 */
	public function removeAddresses()
	{
		if (count($this->getOrder()->addresses)) {
			foreach ($this->getOrder()->addresses as $address) {
				$this->_order->addresses->remove($address->id);
			}
		}

		return $this;
	}

	public function setShipping(ShippingInterface $option)
	{
		$this->_order->shippingName      = $option->getName();
		$this->_order->shippingListPrice = $option->getPrice();

		$event = new Event($this->_order);
		// Dispatch the edit event
		$this->_eventDispatcher->dispatch(
			Events::ASSEMBLER_UPDATE,
			$event
		);

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