<?php

namespace Message\Mothership\Commerce\Order\Entity\Note;

use Message\Mothership\Commerce\Order\Entity\EntityInterface;

use Message\Cog\ValueObject\Authorship;

class Note implements EntityInterface
{
	const TYPE_CHECKOUT   = 'checkout';
	const TYPE_RETURN     = 'return';
	const TYPE_ORDER_VIEW = 'order_view';

	public $id;

	public $order;
	public $authorship;

	public $note;
	public $customerNotified;
	public $raisedFrom;

	public function __construct()
	{
		$this->authorship = new Authorship;

		$this->authorship
			->disableUpdate()
			->disableDelete();
	}
}