<?php

namespace Message\Mothership\Commerce;

use Message\Cog\Event\EventListener as BaseListener;
use Message\Cog\Event\SubscriberInterface;
use Message\Cog\HTTP\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Message\Mothership\Commerce\Order\Events;
use Message\Mothership\ControlPanel\Event\BuildMenuEvent;
use Message\Mothership\Commerce\Order\Event;
use Message\Mothership\Commerce\Order\Entity\Note;

/**
 * Event listener for core Mothership Commerce functionality.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class EventListener extends BaseListener implements SubscriberInterface
{
	/**
	 * {@inheritDoc}
	 */
	static public function getSubscribedEvents()
	{
		return array(
			BuildMenuEvent::BUILD_MAIN_MENU => array(
				array('registerMainMenuItems'),
			),
			Events::BUILD_ORDER_SIDEBAR => array(
				array('registerSidebarItems'),
			),
			Events::BUILD_ORDER_TABS => array(
				array('registerTabItems'),
			),
			Events::ENTITY_CREATE => array(
				array('sendCustomerNotification'),
			),
		);
	}

	/**
	 * Register items to the main menu of the control panel.
	 *
	 * @param BuildMenuEvent $event The event
	 */
	public function registerMainMenuItems(BuildMenuEvent $event)
	{
		$event->addItem('ms.commerce.product.dashboard', 'Products', array('ms.product'));
		$event->addItem('ms.commerce.order.view.dashboard', 'Orders', array('ms.order'));
	}

	/**
	 * Register items to the sidebar of the orders-pages.
	 *
	 * @param BuildMenuEvent $event The event
	 */
	public function registerSidebarItems(BuildMenuEvent $event)
	{
		$event->addItem('ms.commerce.order.view.all', 'All Orders');
		$event->addItem('ms.commerce.order.view.shipped', 'Shipped Orders');
	}

	/**
	 * Register items to the sidebar of the orders-pages.
	 *
	 * @param BuildMenuEvent $event The event
	 */
	public function registerTabItems(Event\BuildOrderTabsEvent $event)
	{
		$event->addItem('ms.commerce.order.detail.view', 			'ms.commerce.order.order.overview-title');
		$event->addItem('ms.commerce.order.detail.view.items', 		'ms.commerce.order.item.listing-title');
		$event->addItem('ms.commerce.order.detail.view.addresses', 	'ms.commerce.order.address.listing-title');
		$event->addItem('ms.commerce.order.detail.view.payments', 	'ms.commerce.order.payment.listing-title');
		$event->addItem('ms.commerce.order.detail.view.dispatches', 'ms.commerce.order.dispatch.listing-title');
		$event->addItem('ms.commerce.order.detail.view.notes', 		'ms.commerce.order.note.listing-title');
		$event->addItem('ms.commerce.order.detail.view.documents', 	'ms.commerce.order.document.listing-title');
	}

	/**
	 * Send a customer a notification.
	 *
	 * @param Event\EntityEvent $event
	 */
	public function sendCustomerNotification(Event\EntityEvent $event)
	{
		$note = $event->getEntity();

		if (!($note instanceof Note\Note)
		 || !$note->customerNotified) {
			return;
		}

		$order    = $event->getOrder();
		$merchant = $this->get('cfg')->merchant;

		$message = $this->get('mail.message');

		$message->setTo($order->user->email, $order->user->getName());
		$message->setSubject('Updates to your ' . $merchant->companyName . ' order ' . $order->orderID);
		$message->setView('Message:Mothership:Commerce::mail:order:note:customer-notification', array(
			'note'     => $note,
			'order'    => $order,
			'merchant' => $merchant,
		));

		$dispatcher = $this->get('mail.dispatcher');

		$dispatcher->send($message);
	}
}