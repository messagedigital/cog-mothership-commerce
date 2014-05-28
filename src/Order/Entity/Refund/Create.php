<?php

namespace Message\Mothership\Commerce\Order\Entity\Refund;

use Message\User\UserInterface;

use Message\Mothership\Commerce\Order;

use Message\Cog\DB;
use Message\Cog\Event\DispatcherInterface;
use Message\Cog\ValueObject\DateTimeImmutable;

use InvalidArgumentException;

/**
 * Order refund creator.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Create implements DB\TransactionalInterface
{
	protected $_loader;
	protected $_eventDispatcher;
	protected $_currentUser;

	protected $_trans;
	protected $_transOverridden = false;

	/**
	 * Constructor.
	 *
	 * @param DB\Transaction      $trans           Database transaction
	 * @param Loader              $loader          Refund loader
	 * @param DispatcherInterface $eventDispatcher Event dispatcher
	 * @param UserInterface       $currentUser     Currently logged in user
	 */
	public function __construct(DB\Transaction $trans, Loader $loader, DispatcherInterface $eventDispatcher, UserInterface $currentUser)
	{
		$this->_trans           = $trans;
		$this->_loader          = $loader;
		$this->_eventDispatcher = $eventDispatcher;
		$this->_currentUser     = $currentUser;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param DB\Transaction $trans The database transaction to use
	 */
	public function setTransaction(DB\Transaction $trans)
	{
		$this->_trans           = $trans;
		$this->_transOverridden = true;
	}

	/**
	 * Create a refund entity.
	 *
	 * @param  Refund $refund The refund to create
	 *
	 * @return Refund         If the transaction was not overwritten, this is
	 *                        a refreshed Refund instance re-loaded from the DB.
	 *                        Otherwise, it's the same instance passed in.
	 */
	public function create(Refund $refund)
	{
		$event = new Order\Event\EntityEvent($refund->order, $refund);
		$event->setTransaction($this->_trans);

		$refund = $this->_eventDispatcher->dispatch(
			Order\Events::ENTITY_CREATE,
			$event
		)->getEntity();

		// Set create authorship data if not already set
		if (!$refund->authorship->createdAt()) {
			$refund->authorship->create(
				new DateTimeImmutable,
				$this->_currentUser->id
			);
		}

		$this->_validate($refund);

		$this->_trans->run('
			INSERT INTO
				order_refund
			SET
				order_id   = :orderID?i,
				payment_id = :paymentID?in,
				return_id  = :returnID?in,
				created_at = :createdAt?d,
				created_by = :createdBy?in,
				method     = :method?sn,
				amount     = :amount?f,
				reason     = :reason?sn,
				reference  = :reference?sn
		', array(
			'orderID'     => $refund->order->id,
			'paymentID'   => $refund->payment ? $refund->payment->id : null,
			'returnID'    => $refund->return ? $refund->return->id : null,
			'createdAt'   => $refund->authorship->createdAt(),
			'createdBy'   => $refund->authorship->createdBy(),
			'method'      => $refund->method->getName(),
			'amount'      => $refund->amount,
			'reason'      => $refund->reason,
			'reference'   => $refund->reference,
		));

		$sqlVariable = 'REFUND_ID_' . spl_object_hash($refund);

		$this->_trans->setIDVariable($sqlVariable);
		$refund->id = '@' . $sqlVariable;

		if (!$this->_transOverridden) {
			$this->_trans->commit();

			return $this->_loader->getByID($this->_trans->getIDVariable($sqlVariable), $refund->order);
		}

		return $refund;
	}

	/**
	 * Validate a Refund for creation.
	 *
	 * @param  Refund $refund
	 *
	 * @throws InvalidArgumentException If the refund does not have an order
	 * @throws InvalidArgumentException If the refund amount is <= 0
	 */
	protected function _validate(Refund $refund)
	{
		if (!$refund->order) {
			throw new InvalidArgumentException('Could not create refund: no order specified');
		}

		if ($refund->amount <= 0) {
			throw new InvalidArgumentException('Could not create refund: amount must be greater than 0');
		}
	}
}