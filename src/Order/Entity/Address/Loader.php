<?php

namespace Message\Mothership\Commerce\Order\Entity\Address;

use Message\Mothership\Commerce\Order;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;

/**
 * Order address loader.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Loader extends Order\Entity\BaseLoader
{
	protected $_query;
	protected $_includeDeleted = false;

	public function __construct(DB\Query $query)
	{
		$this->_query = $query;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getByOrder(Order\Order $order)
	{
		$result = $this->_query->run('
			SELECT
				address_id
			FROM
				order_address
			WHERE
				order_id = ?i
		', $order->id);

		return $this->_load($result->flatten(), true, $order);
	}

	/**
	 * Set whether to load deleted items.
	 *
	 * @param  bool $bool    true / false as to whether to include deleted addresses.
	 *
	 * @return Loader        Loader object in order to chain the methods
	 */
	public function includeDeleted($bool = true)
	{
		$this->_includeDeleted = (bool) $bool;

		return $this;
	}

	public function getByID($id, Order\Order $order = null)
	{
		return $this->_load($id, false, $order);
	}

	protected function _load($ids, $alwaysReturnArray = false, Order\Order $order = null)
	{
		if (!is_array($ids)) {
			$ids = (array) $ids;
		}

		if (!$ids) {
			return $alwaysReturnArray ? array() : false;
		}

		$includeDeleted = $this->_includeDeleted ? '' : 'AND deleted_at IS NULL' ;

		$result = $this->_query->run('
			SELECT
				*,
				address_id AS id,
				country_id AS countryID,
				state_id   AS stateID
			FROM
				order_address
			WHERE
				address_id IN (?ij)
			' . $includeDeleted . '
			ORDER BY
				created_at DESC
		', array($ids));

		if (0 === count($result)) {
			return $alwaysReturnArray ? array() : false;
		}

		$addresses = $result->bindTo('Message\\Mothership\\Commerce\\Order\\Entity\\Address\\Address');
		$return    = array();

		foreach ($result as $key => $row) {
			// Cast address lines to the correct structure
			for ($i = 1; $i <= 4; $i++) {
				$lineKey = 'line_' . $i;

				if ($row->{$lineKey}) {
					$addresses[$key]->lines[$i] = $row->{$lineKey};
				}
			}

			if (!$order || $row->order_id != $order->id) {
				$order = $this->_orderLoader->getByID($row->order_id);
			}

			$addresses[$key]->order = $order;

			$return[$row->id] = $addresses[$key];
		}

		return $alwaysReturnArray || count($return) > 1 ? $return : reset($return);
	}
}