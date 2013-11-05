<?php

namespace Message\Mothership\Commerce\Order\Entity\Note;

use Message\Mothership\Commerce\Order;
use Message\User\UserInterface;
use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;
use Message\Cog\Event\DispatcherInterface;

/**
 * Order note creator.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Create implements DB\TransactionalInterface
{
	protected $_loader;
	protected $_query;
	protected $_currentUser;
	protected $_eventDispatcher;

	public function __construct(DB\Query $query, Loader $loader, UserInterface $currentUser, DispatcherInterface $eventDispatcher)
	{
		$this->_query           = $query;
		$this->_loader          = $loader;
		$this->_currentUser     = $currentUser;
		$this->_eventDispatcher = $eventDispatcher;
	}

	public function setTransaction(DB\Transaction $trans)
	{
		$this->_query = $trans;
	}

	public function create(Note $note)
	{
		$event = new Order\Event\EntityEvent($note->order, $note);

		if ($this->_query instanceof DB\Transaction) {
			$event->setTransaction($this->_query);
		}

		$note = $this->_eventDispatcher->dispatch(
			Order\Events::ENTITY_CREATE,
			$event
		)->getEntity();

		// Set create authorship data if not already set
		if (!$note->authorship->createdAt()) {
			$note->authorship->create(
				new DateTimeImmutable,
				$this->_currentUser->id
			);
		}

		$result = $this->_query->run('
			INSERT INTO
				order_note
			SET
				order_id          = :orderID?i,
				created_at        = :createdAt?i,
				created_by        = :createdBy?in,
				note              = :note?s,
				customer_notified = :customerNotified?b,
				raised_from       = :raisedFrom?sn
		', array(
			'orderID'          => $note->order->id,
			'createdAt'        => $note->authorship->createdAt(),
			'createdBy'        => $note->authorship->createdBy(),
			'note'             => $note->note,
			'customerNotified' => $note->customerNotified,
			'raisedFrom'       => $note->raisedFrom,
		));

		if ($this->_query instanceof DB\Transaction) {
			return $note;
		}

		return $this->_loader->getByID($result->id(), $note->order);
	}
}