<?php

namespace Message\Mothership\Commerce\Order\Entity\Address;

use Message\Cog\ValueObject\DateTimeImmutable;
use Message\Cog\ValueObject\Authorship;

use Message\Cog\DB;
use Message\User\UserInterface;

/**
 * Decorator for deleting addresses.
 *
 * @author Eleanor Shakeshaft <eleanor@message.co.uk>
 */
class Delete implements DB\TransactionalInterface
{
	protected $_query;
	protected $_currentUser;

	/**
	 * Constructor.
	 *
	 * @param DB\Query      $query       The database query instance to use
	 * @param UserInterface $currentUser The currently logged in user
	 */
	public function __construct(DB\Query $query, UserInterface $user, Loader $loader)
	{
		$this->_query       = $query;
		$this->_currentUser = $user;
		$this->_loader      = $loader;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return Delete Returns $this for chainability
	 */
	public function setTransaction(DB\Transaction $transaction)
	{
		$this->_query = $transaction;

		return $this;
	}

	/**
	 * Delete a address by marking it as deleted in the database.
	 *
	 * @param  Address $address The new address which is replacing the address
	 *                          to be deleted
	 */
	public function delete(Address $address)
	{
		$old_id = $this->_query->run('
			SELECT
				MAX(address_id) AS address_id
			FROM order_address WHERE order_id = :orderID?i
			AND type = :type?s
		', array(
			'orderID'   => $address->order->id,
			'type'      => $address->type,
		));

		$old_address = $this->_loader->getByID($old_id->flatten());
		$old_address->authorship->delete(new DateTimeImmutable, $this->_currentUser->id);

		$this->_query->run('
			UPDATE
				order_address,
				(SELECT MAX(address_id) AS address_id
					FROM order_address WHERE order_id = :orderID?i
					AND type = :type?s
				) t
			SET
				deleted_at = :createdAt?d,
				deleted_by = :createdBy?in
			WHERE
				order_address.address_id = t.address_id'
		, array(
			'orderID'   => $old_address->order->id,
			'createdAt' => $old_address->authorship->deletedAt(),
			'createdBy' => $old_address->authorship->deletedBy(),
			'type'      => $old_address->type,
		));
	}
}
