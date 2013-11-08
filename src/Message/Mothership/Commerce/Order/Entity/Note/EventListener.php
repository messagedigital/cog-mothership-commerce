<?php

namespace Message\Mothership\Commerce\Order\Entity\Note;

use Message\Mothership\Commerce\Order\Events as OrderEvents;
use Message\Mothership\Commerce\Order\Event;

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
			OrderEvents::ENTITY_CREATE => array(
				array('sendCustomerNotification'),
			),
		);
	}

	/**
	 * Send a customer a notification.
	 *
	 * @param Event\EntityEvent $event
	 */
	public function sendCustomerNotification(Event\EntityEvent $event)
	{
		$note = $event->getEntity();

		if (! $note instanceof Note or ! $note->customerNotified) {
			return false;
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