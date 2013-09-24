<?php

namespace Message\Mothership\Commerce\Order\Entity\Refund;

use Message\User\UserInterface;

use Message\Mothership\Commerce\Order;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;

/**
 * Order refund creator.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Edit implements DB\TransactionalInterface
{
	protected $_query;
	protected $_loader;
	protected $_currentUser;

	public function __construct(DB\Query $query, Loader $loader, UserInterface $currentUser)
	{
		$this->_query       = $query;
		$this->_loader      = $loader;
		$this->_currentUser = $currentUser;
	}

	public function setTransaction(DB\Transaction $trans)
	{
		$this->_query = $trans;
	}

	public function setPayment(Refund $refund, Payment $payment)
	{
		$this->_query->run('
			UPDATE
				order_refund
			SET
				payment_id = ?i
			WHERE
				refund_id = ?i
		', array(
			$payment->id,
			$refund->id,
		));
	}
}