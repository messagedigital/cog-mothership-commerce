<?php

namespace Message\Mothership\Commerce\Order\Entity\Item;

use Message\Mothership\Commerce\Order\Events as OrderEvents;
use Message\Mothership\Commerce\Order\Event;
use Message\Mothership\Commerce\Order\Status\Status as BaseStatus;

use Message\Cog\Event\SubscriberInterface;

/**
 * Order item event listener.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class EventListener implements SubscriberInterface
{
	protected $_defaultStatus;

	/**
	 * {@inheritdoc}
	 */
	static public function getSubscribedEvents()
	{
		return array(
			OrderEvents::CREATE_START => array(
				array('calculateTax'),
				array('clearTax'),
				array('setDefaultStatus'),
			),
			OrderEvents::CREATE_VALIDATE => array(
				array('checkItemSet')
			),
			OrderEvents::ASSEMBLER_UPDATE => array(
				array('calculateTax'),
				array('clearTax'),
			),
			OrderEvents::CREATE_ITEM => array(
				array('calculateTax'),
				array('clearTax'),
				array('setDefaultStatus'),
			),
		);
	}

	/**
	 * Constructor.
	 *
	 * @param BaseStatus $defaultStatus The default status to set on new order items
	 */
	public function __construct(BaseStatus $defaultStatus)
	{
		$this->_defaultStatus = $defaultStatus;
	}

	/**
	 * Set the default statuses on all items that don't already have a status set.
	 *
	 * @param Event $event The event object
	 */
	public function setDefaultStatus(Event\Event $event)
	{
		foreach ($event->getOrder()->items as $item) {
			if (!$item->status) {
				$item->status = new Status\Status($this->_defaultStatus->code, $this->_defaultStatus->name);
			}
		}
	}

	/**
	 * Calculate the gross, tax and net amounts for each item in an order before
	 * it gets created in the database.
	 *
	 * This event is skipped if the order is not taxable.
	 *
	 * @param Event $event The event object
	 */
	public function calculateTax(Event\Event $event)
	{
		$order = $event->getOrder();

		if (!$order->taxable) {
			return false;
		}

		foreach ($order->items as $item) {
			// Set the tax rate to whatever the product's tax rate is, if not already set
			if (!$item->taxRate) {
				$item->taxRate = $item->productTaxRate;
			}

			// Set the gross to the list price minus the discount
			$item->gross = round($item->listPrice - $item->discount, 2);

			// Calculate tax where the strategy is exclusive
			if ('exclusive' === $item->taxStrategy) {
				$item->tax    = round($item->gross * ($item->taxRate / 100), 2);
				$item->gross += $item->tax;
			}
			// Calculate tax where the strategy is inclusive
			else {
				$item->tax = round(($item->gross / (100 + $item->taxRate)) * $item->taxRate, 2);
			}

			// Set the net value to gross - tax
			$item->net = round($item->gross - $item->tax, 2);
		}
	}

	/**
	 * Clear the tax amounts on all items in the order, and reset the gross &
	 * net amounts.
	 *
	 * This event is skipped if the order is taxable.
	 *
	 * @param Event\Event $event The event object
	 */
	public function clearTax(Event\Event $event)
	{
		$order = $event->getOrder();

		if ($order->taxable) {
			return false;
		}

		foreach ($order->items as $item) {
			// Resetting the gross is important because if the tax strategy is
			// exclusive this will include the tax amount
			$item->gross   = round($item->listPrice - $item->discount, 2);

			$item->taxRate = 0;
			$item->tax     = 0;
			$item->net     = $item->gross;
		}
	}

	public function checkItemSet(Event\ValidateEvent $event)
	{
		if (count($event->getOrder()->items) < 1) {
			$event->addError('Order must have at least one item to be created');
		}
	}
}