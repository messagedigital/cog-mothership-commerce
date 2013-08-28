<?php

namespace Message\Mothership\Commerce\Order\Entity\Address;

use Message\Mothership\Commerce\Order\Entity\Collection as BaseCollection;

class Collection extends BaseCollection
{
	public function getByType($type)
	{
		$addresses = $this->getByProperty('type', $type);

		if (count($addresses) > 1) {
			throw new \UnexpectedValueException(sprintf(
				'Order has more than one `%s` address',
				$type
			));
		}

		return current($addresses) ?: false;
	}
}