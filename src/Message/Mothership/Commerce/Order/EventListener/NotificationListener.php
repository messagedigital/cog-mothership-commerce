<?php

namespace Message\Mothership\Commerce\Order\EventListener;

use Message\Mothership\Commerce\Order\Events as OrderEvents;
use Message\Mothership\Commerce\Order\Event;

use Message\Cog\Event\EventListener as BaseListener;
use Message\Cog\Event\SubscriberInterface;
use Message\Cog\Event\DispatcherInterface;

/**
 * Order event listener for sending customer notifications.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class NotificationListener extends BaseListener implements SubscriberInterface
{
	/**
	 * {@inheritdoc}
	 */
	static public function getSubscribedEvents()
	{
		return array(
			OrderEvents::DISPATCH_SHIPPED => array(
				array('triggerSendEvent')
			),
			OrderEvents::DISPATCH_NOTIFICATION => array(
				array('sendDispatchShippedNotification'),
			),
		);
	}

	/**
	 * Separate event for notification and shipping, so that they can be treated separately in certain scenarios
	 *
	 * @param Event\DispatchEvent $event
	 */
	public function triggerSendEvent(Event\DispatchEvent $event)
	{
		$event->getDispatcher()->dispatch(OrderEvents::DISPATCH_NOTIFICATION, $event);
	}

	/**
	 * Send a customer a notification the dispatch has been shipped.
	 *
	 * @param  Event\DispatchEvent $event
	 */
	public function sendDispatchShippedNotification(Event\DispatchEvent $event)
	{
		$dispatch = $event->getDispatch();
		$order    = $event->getOrder();
		$merchant = $this->get('cfg')->merchant;

		$message = $this->get('mail.message');

		$message->setTo($order->user->email, $order->user->getName());
		$message->setSubject('Your ' . $merchant->companyName . ' order ' . $order->orderID . ' has shipped');
		$message->setView('Message:Mothership:Commerce::mail:order:dispatch:shipped-notification', array(
			'dispatch' => $dispatch,
			'order'    => $order,
			'merchant' => $merchant,
		));

		$dispatcher = $this->get('mail.dispatcher');

		$dispatcher->send($message);
	}
}