<?php

namespace Message\Mothership\Commerce\Order\EventListener;

use Message\Mothership\Commerce\Order\Events as OrderEvents;
use Message\Mothership\Commerce\Order\Event;

use Message\Mothership\Commerce\Order\Entity\Address\Address;

use Message\Mothership\Commerce\CountryList;

use Message\Cog\Event\EventListener as BaseListener;
use Message\Cog\Event\SubscriberInterface;

/**
 * Event listener for installations that charge VAT within the EU to customers.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class VatListener implements SubscriberInterface
{
	protected $_countries;

	/**
	 * {@inheritDoc}
	 */
	static public function getSubscribedEvents()
	{
		return array(
			OrderEvents::CREATE_START => array(
				array('setTaxable', 100),
			),
			OrderEvents::ASSEMBLER_ADDRESS_UPDATE => array(
				array('setTaxable', 100),
			),
		);
	}

	/**
	 * Constructor.
	 *
	 * @param CountryList $countries Country list class
	 */
	public function __construct(CountryList $countries)
	{
		$this->_countries = $countries;
	}

	/**
	 * Set the order to taxable if the delivery country is within the EU.
	 * Otherwise, set it to not taxable.
	 *
	 * @param Event $event The event object
	 */
	public function setTaxable(Event\Event $event)
	{
		$order           = $event->getOrder();
		$deliveryCountry = $order->getAddress(Address::DELIVERY)->countryID;
		$order->taxable  = (bool) $this->_countries->isInEU($deliveryCountry);
	}
}