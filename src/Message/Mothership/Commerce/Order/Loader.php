<?php

namespace Message\Mothership\Commerce\Order;

use Message\User;

use Message\Cog\DB;

use Message\Cog\ValueObject\Authorship;
use Message\Cog\ValueObject\DateTimeImmutable;

use Message\User\UserInterface;

/**
 * Decorator for loading orders.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Loader
{
	protected $_query;
	protected $_eventDispatcher;
	protected $_userLoader;
	protected $_statuses;
	protected $_itemStatuses;
	protected $_entities;
	protected $_orderBy;

	public function __construct(DB\Query $query, User\Loader $userLoader,
		Status\Collection $statuses, Status\Collection $itemStatuses, array $entities)
	{
		$this->_query        = $query;
		$this->_userLoader   = $userLoader;
		$this->_statuses     = $statuses;
		$this->_itemStatuses = $itemStatuses;
		$this->_entities     = $entities;
	}

	public function getEntities()
	{
		$return = array();

		foreach ($this->_entities as $name => $loader) {
			$return[$name] = clone $loader;
		}

		return $return;
	}

	/**
	 * Get the loader for a specific entity.
	 *
	 * @param  string $name Entity name
	 *
	 * @return Entity\LoaderInterface The entity loader
	 */
	public function getEntityLoader($name)
	{
		if (!array_key_exists($name, $this->_entities)) {
			throw new \InvalidArgumentException(sprintf('Unknown order entity: `%s`', $name));
		}

		$loader = $this->_entities[$name]->getLoader();
		$loader->setOrderLoader($this);

		return $loader;
	}

	/**
	 * Get a specific order or orders by ID.
	 *
	 * @param  int|array $id            The order ID, or array of order IDs
	 *
	 * @return Order|array[Order]|false The order, or false if it doesn't exist
	 */
	public function getByID($id)
	{
		if (is_array($id)) {
			return $this->_load($id, true);
		}

		return $this->_load($id);
	}

	/**
	 * Get all orders placed by a specific user.
	 *
	 * @param  User $user   The user to get orders for
	 *
	 * @return array[Order] Array of orders
	 */
	public function getByUser(User\User $user)
	{
		$result = $this->_query->run('
			SELECT
				order_id
			FROM
				order_summary
			WHERE
				user_id = ?i
		', $user->id);

		return $this->_load($result->flatten(), true);
	}

	/**
	 * Get orders with specific statuses.
	 *
	 * @param  int|array $statuses Status code or array of status codes
	 *
	 * @return array[Order]        Array of orders
	 *
	 * @throws \InvalidArgumentException If any status codes are not known
	 */
	public function getByStatus($statuses, $limit = 9999)
	{
		if (!is_array($statuses)) {
			$statuses = (array) $statuses;
		}

		foreach ($statuses as $code) {
			if (!$this->_statuses->exists($code)) {
				throw new \InvalidArgumentException(sprintf('Unknown order status code: `%s`', $code));
			}
		}

		$result = $this->_query->run('
			SELECT
				order_id
			FROM
				order_summary
			WHERE
				status_code IN (?ij)
			ORDER BY created_at DESC
			LIMIT ?i
		', array($statuses, $limit));

		$this->_orderBy = 'order_summary.created_at DESC';

		return $this->_load($result->flatten(), true);
	}

	/**
	 * Get orders for items with a specific current status.
	 *
	 * At least one item in the order must have one of the given statuses as its
	 * most recent (current) status.
	 *
	 * @param  int|array $statuses Status code or array of status codes
	 *
	 * @return array[Order]        Array of orders
	 *
	 * @throws \InvalidArgumentException If any item status codes are not known
	 */
	public function getByCurrentItemStatus($statuses)
	{
		if (!is_array($statuses)) {
			$statuses = (array) $statuses;
		}

		foreach ($statuses as $code) {
			if (!$this->_itemStatuses->exists($code)) {
				throw new \InvalidArgumentException(sprintf('Unkown order item status code: `%s`', $code));
			}
		}


		// Order by created_at DESC, status_code DESC to solve issue:
		// https://github.com/messagedigital/uniform_wares/issues/320
		// Where pick & pack statuses have exactly the same timestamp when actioned together.
		$result = $this->_query->run('
			SELECT
				order_item.order_id,
				status_code
			FROM
				order_item
			JOIN (
				SELECT
					*
				FROM
					order_item_status
				ORDER BY
					order_item_status.created_at DESC, order_item_status.status_code DESC
			) AS statuses USING (item_id)
			GROUP BY
				item_id
			HAVING
				status_code IN (?ij)
		', array((array) $statuses));

		return $this->_load(array_unique($result->flatten()), true);
	}

	public function getByTrackingCode($code)
	{
		$result = $this->_query->run('
			SELECT
				os.order_id
			FROM
				order_summary os
			LEFT JOIN
				order_dispatch od ON os.order_id = od.order_id
			WHERE
				od.code = ?s
		', $code);

		return $this->_load($result->flatten(), true);
	}

	protected function _load($ids, $returnArray = false)
	{
		$orderBy = $this->_orderBy ? 'ORDER BY ' . $this->_orderBy : '';
		$this->_orderBy = '';

		if (!is_array($ids)) {
			$ids = (array) $ids;
		}

		if (0 === count($ids)) {
			return $returnArray ? array() : false;
		}

		$result = $this->_query->run('
			SELECT
				order_summary.*,
				order_summary.order_id         AS id,
				order_summary.order_id         AS orderID,
				order_summary.user_email	   AS userEmail,
				order_summary.currency_id      AS currencyID,
				order_summary.conversion_rate  AS conversionRate,
				order_summary.product_net      AS productNet,
				order_summary.product_discount AS productDiscount,
				order_summary.product_tax      AS productTax,
				order_summary.product_gross    AS productGross,
				order_summary.total_net        AS totalNet,
				order_summary.total_discount   AS totalDiscount,
				order_summary.total_tax        AS totalTax,
				order_summary.total_gross      AS totalGross,
				order_shipping.name            AS shippingName,
				order_shipping.display_name    AS shippingDisplayName,
				order_shipping.list_price      AS shippingListPrice,
				order_shipping.net             AS shippingNet,
				order_shipping.discount        AS shippingDiscount,
				order_shipping.tax             AS shippingTax,
				order_shipping.tax_rate        AS shippingTaxRate,
				order_shipping.gross           AS shippingGross
			FROM
				order_summary
			LEFT JOIN
				order_shipping USING (order_id)
			WHERE
				order_summary.order_id IN (?ij)
			GROUP BY
				order_summary.order_id
			' . ($orderBy) . '
		', array($ids));

		if (0 === count($result)) {
			return $returnArray ? array() : false;
		}

		$self       = $this;
		$userLoader = $this->_userLoader;
		$statuses   = $this->_statuses;
		$query      = $this->_query;

		$orders = $result->bindWith(function($row) use ($self, $userLoader, $statuses, $query)
		{
			$order = new Order($self->getEntities());

			foreach ($row as $k => $v) {
				if (property_exists($order, $k)) {
					$order->$k = $v;
				}
			}

			// Cast decimals to float
			$order->conversionRate    = (float) $row->conversionRate;
			$order->productNet        = (float) $row->productNet;
			$order->productDiscount   = (float) $row->productDiscount;
			$order->productTax        = (float) $row->productTax;
			$order->productGross      = (float) $row->productGross;
			$order->totalNet          = (float) $row->totalNet;
			$order->totalDiscount     = (float) $row->totalDiscount;
			$order->totalTax          = (float) $row->totalTax;
			$order->totalGross        = (float) $row->totalGross;
			$order->shippingListPrice = (float) $row->shippingListPrice;
			$order->shippingNet       = (float) $row->shippingNet;
			$order->shippingDiscount  = (float) $row->shippingDiscount;
			$order->shippingTax       = (float) $row->shippingTax;
			$order->shippingTaxRate   = (float) $row->shippingTaxRate;
			$order->shippingGross     = (float) $row->shippingGross;

			$order->taxable = (bool) $row->taxable;

			$order->user = $userLoader->getByID($row->user_id);

			$order->authorship->create(
				new DateTimeImmutable(date('c', $row->created_at)),
				$row->created_by
			);

			if ($row->updated_at) {
				$order->authorship->update(
					new DateTimeImmutable(date('c', $row->updated_at)),
					$row->updated_by
				);
			}

			$order->status = $statuses->get($row->status_code);

			$result = $query->run('
				SELECT
					`key`,
					`value`
				FROM
					order_metadata
				WHERE
					order_id = ?i
			', $order->id);

			foreach ($result->hash('key', 'value') as $key => $value) {
				$order->metadata->set($key, $value);
			}

			return $order;
		});

		return $returnArray ? $orders : reset($orders);
	}
}