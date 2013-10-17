<?php

namespace Message\Mothership\Commerce\Order\Entity\Address;

use Message\Mothership\Commerce\Order\Entity\Collection as BaseCollection;

class Collection extends BaseCollection
{
	public function getByType($type)
	{
		$addresses = $this->getByProperty('type', $type);

		ksort($addresses);

		return end($addresses) ?: false;
	}
}