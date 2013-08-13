<?php

namespace Message\Mothership\Commerce\Order\Entity\Item;

use Message\Mothership\Commerce\Order\Events as OrderEvents;
use Message\Mothership\Commerce\Order\Event;
use Message\Mothership\Commerce\Order\Status\Collection as StatusCollection;

use Message\User\UserInterface;

use Message\Cog\DB;
use Message\Cog\Event\DispatcherInterface;
use Message\Cog\ValueObject\DateTimeImmutable;

class Edit implements DB\TransactionalInterface
{
	protected $_query;
	protected $_transOverriden = false;

	protected $_eventDispatcher;
	protected $_statuses;
	protected $_currentUser;

	public function __construct(DB\Transaction $query, DispatcherInterface $dispatcher,
		StatusCollection $statuses, UserInterface $currentUser)
	{
		$this->_query           = $query;
		$this->_eventDispatcher = $dispatcher;
		$this->_statuses        = $statuses;
		$this->_currentUser     = $currentUser;
	}

	public function setTransaction(DB\Transaction $trans)
	{
		$this->_query          = $trans;
		$this->_transOverriden = true;
	}

	public function updateStatus($items, $statusCode)
	{
		if (!$this->_statuses->exists($statusCode)) {
			throw new \InvalidArgumentException(sprintf('Order item status `%s` does not exist', $statusCode));
		}

		$status = $this->_statuses->get($statusCode);

		if (!is_array($items)) {
			$items = array($items);
		}

		foreach ($items as $key => $item) {
			if (!($item instanceof Item)) {
				throw new \InvalidArgumentException(sprintf('Unexpected value: expected order item instance'));
			}

			// Skip if the item is already at this status
			if ($status->code === $item->status->code) {
				return false;
			}

			// Get instance of item status (so we have authorship info)
			$status = new Status\Status($status->code, $status->name);

			$status->authorship->create(
				new DateTimeImmutable,
				$this->_currentUser->id
			);

			$this->_query->add('
				INSERT INTO
					order_item_status
				SET
					order_id    = :orderID?i,
					item_id     = :itemID?i,
					status_code = :status?i,
					created_at  = :createdAt?i,
					created_by  = :createdBy?in
			', array(
				'orderID'   => $item->order->id,
				'itemID'    => $item->id,
				'status'    => $status->code,
				'createdAt' => $status->authorship->createdAt(),
				'createdBy' => $status->authorship->createdBy(),
			));

			$item->status = $status;
		}

		$event = new Event\TransactionalEvent($item->order);
		$event->setTransaction($this->_query);

		$this->_eventDispatcher->dispatch(
			OrderEvents::ITEM_STATUS_CHANGE,
			$event
		);

		if (!$this->_transOverriden) {
			$this->_query->commit();
		}

		return $this;
	}
}