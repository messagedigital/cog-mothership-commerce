<?php

namespace Message\Mothership\Commerce\Order\Entity\Item;

use Message\Mothership\Commerce\Order\Entity\Collection as BaseCollection;

class Collection extends BaseCollection
{
	public function getRows()
	{
		$rows = array();

		foreach ($this->all() as $item) {
			if (!array_key_exists($item->unitID, $rows)) {
				$rows[$item->unitID] = new Row;
			}

			$rows[$item->unitID]->add($item);
		}

		return $rows;
	}
}