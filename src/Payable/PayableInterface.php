<?php

namespace Message\Mothership\Commerce\Payable;

use Message\Cog\ValueObject\Collection;

/**
 * Interface for objects that represent something for which a payment can be
 * made. This includes purchases made by customers, refunds and arbitrary
 * payments made by any user.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
interface PayableInterface
{
	/**
	 * Get the amount to be paid.
	 *
	 * @return float
	 */
	public function getPayableAmount();

	/**
	 * Get the currency with which to make the payment.
	 *
	 * @return string
	 */
	public function getPayableCurrency();

	/**
	 * Get an address associated with the payable.
	 *
	 * @param  string $type
	 * @return \Message\Mothership\Commerce\Address\Address
	 */
	public function getPayableAddress($type);

	/**
	 * Get a unique transaction id for this payable.
	 *
	 * @return string
	 */
	public function getPayableTransactionID();

	/**
	 * Get all the payments against the object
	 *
	 * @return array | \Message\Cog\ValueObject\Collection
	 */
	public function getPayments();
}