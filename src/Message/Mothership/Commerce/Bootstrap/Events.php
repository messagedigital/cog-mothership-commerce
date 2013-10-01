<?php

namespace Message\Mothership\Commerce\Bootstrap;

use Message\Mothership\Commerce;

use Message\Cog\Bootstrap\EventsInterface;
use Message\Cog\Service\ContainerInterface;
use Message\Cog\Service\ContainerAwareInterface;

class Events implements EventsInterface, ContainerAwareInterface
{
	protected $_services;

	public function setContainer(ContainerInterface $container)
	{
		$this->_services = $container;
	}

	public function registerEvents($dispatcher)
	{
		$dispatcher->addSubscriber(new Commerce\Order\EventListener\AssemblerListener);
		$dispatcher->addSubscriber(new Commerce\Order\EventListener\TotalsListener);
		$dispatcher->addSubscriber(new Commerce\Order\EventListener\ValidateListener);
		$dispatcher->addSubscriber(new Commerce\Order\EventListener\StatusListener(
			$this->_services['order.statuses'],
			$this->_services['order.edit']
		));
		$dispatcher->addSubscriber(new Commerce\Order\EventListener\NotificationListener);

		$dispatcher->addSubscriber($this->_services['order.listener.vat']);

		$dispatcher->addSubscriber(new Commerce\Order\Entity\Address\EventListener);
		$dispatcher->addSubscriber(new Commerce\Order\Entity\Discount\EventListener);
		$dispatcher->addSubscriber(new Commerce\Order\Entity\Item\EventListener(
			$this->_services['order.item.statuses']->get(0)
		));

		$dispatcher->addSubscriber(new Commerce\EventListener);
		$dispatcher->addSubscriber(new Commerce\Order\Basket\EventListener);
	}
}