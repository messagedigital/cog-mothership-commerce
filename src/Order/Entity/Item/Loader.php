<?php

namespace Message\Mothership\Commerce\Order\Entity\Item;

use Message\Mothership\Commerce\Order;
use Message\Mothership\Commerce\Product\Stock\Location\Collection as LocationCollection;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;
use Message\Cog\DB\Entity\EntityLoaderCollection;

/**
 * Order item loader.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Loader extends Order\Entity\BaseLoader implements
	Order\Transaction\DeletableRecordLoaderInterface,
	Order\Entity\DeletableLoaderInterface
{
	protected $_query;
	protected $_statusLoader;
	protected $_stockLocations;
	protected $_entityLoaders;
	protected $_includeDeleted = false;

	public function __construct(
		DB\Query $query,
		Status\Loader $statusLoader,
		LocationCollection $stockLocations,
		EntityLoaderCollection $entityLoaders
	) {
		$this->_query          = $query;
		$this->_statusLoader   = $statusLoader;
		$this->_stockLocations = $stockLocations;
	}

	/**
	 * Set whether to load deleted items. Also sets include deleted on order loader.
	 *
	 * @param  bool $bool    true / false as to whether to include deleted items
	 *
	 * @return Loader        Loader object in order to chain the methods
	 */
	public function includeDeleted($bool = true)
	{
		$this->_includeDeleted = (bool) $bool;
		$this->_orderLoader->includeDeleted($this->_includeDeleted);

		return $this;
	}

	public function getByID($id, Order\Order $order = null)
	{
		return $this->_load($id, false, $order);
	}

	/**
	 * Alias of getByID for Order\Transaction\RecordLoaderInterface
	 * @param  int $id record id
	 * @return Item|false The item, or false if it doesn't exist
	 */
	public function getByRecordID($id)
	{
		return $this->getByID($id);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getByOrder(Order\Order $order)
	{
		$result = $this->_query->run('
			SELECT
				item_id
			FROM
				order_item
			WHERE
				order_id = ?i
		', $order->id);

		return $this->_load($result->flatten(), true, $order);
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
				item_id          AS id,
				order_id         AS orderID,
				deleted_at       AS deletedAt,
				deleted_by       AS deletedBy,
				list_price       AS listPrice,
				actual_price     AS actualPrice,
				base_price       AS basePrice,
				tax_rate         AS taxRate,
				product_tax_rate AS productTaxRate,
				tax_strategy     AS taxStrategy,
				product_id       AS productID,
				product_name     AS productName,
				unit_id          AS unitID,
				unit_revision    AS unitRevision,
				weight_grams     AS weight
			FROM
				order_item
			LEFT JOIN
				order_item_personalisation USING (item_id)
			WHERE
				item_id IN (?ij)
			' . $includeDeleted . '
		', array($ids));

		if (0 === count($result)) {
			return $alwaysReturnArray ? array() : false;
		}

		$items  = $result->bindTo(
			'Message\\Mothership\\Commerce\\Order\\Entity\\Item\\ItemProxy',
			[$this->_entityLoaders]
		);
		
		$return = array();

		foreach ($result as $key => $row) {
			// Cast decimals to float
			$items[$key]->listPrice      = (float) $row->listPrice;
			$items[$key]->actualPrice    = (float) $row->actualPrice;
			$items[$key]->basePrice      = (float) $row->basePrice;
			$items[$key]->net            = (float) $row->net;
			$items[$key]->discount       = (float) $row->discount;
			$items[$key]->tax            = (float) $row->tax;
			$items[$key]->taxRate        = (float) $row->taxRate;
			$items[$key]->productTaxRate = (float) $row->productTaxRate;
			$items[$key]->gross          = (float) $row->gross;
			$items[$key]->rrp            = (float) $row->rrp;

			// Set authorship data
			$items[$key]->authorship->create(
				new DateTimeImmutable(date('c', $row->created_at)),
				$row->created_by
			);

			if ($row->deleted_at) {
				$items[$key]->authorship->delete(
					new DateTimeImmutable(date('c', $row->deleted_at)),
					$row->deleted_by
				);
			}

			// Load the order if we don't have it already
			if (!$order || $row->order_id != $order->id) {
				$order = $this->_orderLoader->getByID($row->order_id);
			}

			// Set the order on the item
			$items[$key]->order = $order;

			// Set the current status
			$this->_statusLoader->setLatest($items[$key]);

			// Set the stock location
			$items[$key]->stockLocation = $this->_stockLocations->get($row->stock_location);

			// Load personalisation data
			$personalisation = $this->_query->run('
				SELECT
					name,
					value
				FROM
					order_item_personalisation
				WHERE
					item_id = ?i
			', $items[$key]->id);

			foreach ($personalisation->hash('name', 'value') as $name => $value) {
				$items[$key]->personalisation->{$name} = $value;
			}

			$return[$row->id] = $items[$key];
		}

		return $alwaysReturnArray || count($return) > 1 ? $return : reset($return);
	}
}