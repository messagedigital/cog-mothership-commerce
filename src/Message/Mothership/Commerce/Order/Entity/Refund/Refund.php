<?php

namespace Message\Mothership\Commerce\Order\Entity\Refund;

use Message\Mothership\Commerce\Order\Entity\EntityInterface;

use Message\Cog\ValueObject\Authorship;

class Refund implements EntityInterface
{
	public $id;

	public $order;
	public $authorship;
	public $payment;
	public $return;

	public $method;
	public $amount;
	public $reason;
	public $reference;

	public function __construct()
	{
		$this->authorship = new Authorship;

		$this->authorship
			->disableUpdate()
			->disableDelete();
	}
}