<?php

namespace Message\Mothership\Commerce\Order\Entity\Item;

use Message\Mothership\Commerce\Order;
use Message\Mothership\Commerce\Product\Stock\Location\Collection as LocationCollection;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;

/**
 * Order item loader.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Loader extends Order\Entity\BaseLoader
{
	protected $_query;
	protected $_statusLoader;
	protected $_stockLocations;

	public function __construct(DB\Query $query, Status\Loader $statusLoader, LocationCollection $stockLocations)
	{
		$this->_query          = $query;
		$this->_statusLoader   = $statusLoader;
		$this->_stockLocations = $stockLocations;
	}

	public function getByID($id)
	{
		return $this->_load($id, false);
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

		$result = $this->_query->run('
			SELECT
				*,
				item_id       AS id,
				order_id      AS orderID,
				list_price    AS listPrice,
				tax_rate      AS taxRate,
				product_id    AS productID,
				product_name  AS productName,
				unit_id       AS unitID,
				unit_revision AS unitRevision,
				weight_grams  AS weight
			FROM
				order_item
			LEFT JOIN
				order_item_personalisation USING (item_id)
			WHERE
				item_id IN (?ij)
		', array($ids));

		if (0 === count($result)) {
			return $alwaysReturnArray ? array() : false;
		}

		$items  = $result->bindTo('Message\\Mothership\\Commerce\\Order\\Entity\\Item\\Item');
		$return = array();

		foreach ($result as $key => $row) {
			// Cast decimals to float
			$items[$key]->listPrice = (float) $row->listPrice;
			$items[$key]->net       = (float) $row->net;
			$items[$key]->discount  = (float) $row->discount;
			$items[$key]->tax       = (float) $row->tax;
			$items[$key]->taxRate   = (float) $row->taxRate;
			$items[$key]->gross     = (float) $row->gross;
			$items[$key]->rrp       = (float) $row->rrp;

			// Set authorship data
			$items[$key]->authorship->create(
				new DateTimeImmutable(date('c', $row->created_at)),
				$row->created_by
			);

			// Load the order if we don't have it already
			if (!$order) {
				$order = $this->_orderLoader->getByID($row->order_id);
			}

			// Set the order on the item
			$items[$key]->order = $order;

			// Set the current status
			$this->_statusLoader->setLatest($items[$key]);

			// Set the stock location
			$items[$key]->stockLocation = $this->_stockLocations->get($row->stock_location);

			// Add personalisation data, if set
			if ($row->sender_name || $row->recipient_name || $row->recipient_email || $row->message) {
				$items[$key]->personalisation = new Personalisation;
				$items[$key]->personalisation->senderName     = $row->sender_name;
				$items[$key]->personalisation->recipientName  = $row->recipient_name;
				$items[$key]->personalisation->recipientEmail = $row->recipient_email;
				$items[$key]->personalisation->message        = $row->message;
			}

			$return[$row->id] = $items[$key];
		}

		return $alwaysReturnArray || count($return) > 1 ? $return : reset($return);
	}
}