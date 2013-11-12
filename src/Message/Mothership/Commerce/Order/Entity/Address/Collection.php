<?php

namespace Message\Mothership\Commerce\Order\Entity\Address;

use Message\Mothership\Commerce\Order\Entity\Collection as BaseCollection;

class Collection extends BaseCollection
{
	public function getByType($type)
	{
		$addresses = $this->getByProperty('type', $type);

		return current($addresses) ?: false;
	}

	public function getAllByType($type)
	{
		return $this->getByProperty('type', $type);
	}
}