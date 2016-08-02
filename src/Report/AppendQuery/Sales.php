<?php

namespace Message\Mothership\Commerce\Report\AppendQuery;

use Message\Cog\DB\QueryBuilderFactory;
use Message\Mothership\Report\Filter;
use Message\Mothership\Report\Report\AppendQuery\FilterableInterface;

class Sales implements FilterableInterface
{
	private $_builderFactory;
	private $_filters;

	/**
	 * Constructor.
	 *
	 * @param QueryBuilderFactory   $builderFactory
	 */
	public function __construct(QueryBuilderFactory $builderFactory)
	{
		$this->_builderFactory = $builderFactory;
	}

	/**
	 * Sets the filters from the report.
	 *
	 * @param Filter\Collection $filters
	 *
	 * @return  $this  Return $this for chainability
	 */
	public function setFilters(Filter\Collection $filters)
	{
		$this->_filters = $filters;

		return $this;
	}

	/**
	 * Gets all ORDER/SALES data where:
	 * Order status is not CANCELLED (-300) or PAYMENT_PENDING (-100).
	 * Product is not a VOUCHER.
	 * Item is not an EXCHANGE ITEM.
	 *
	 * All columns must match the other sub-queries used in SALES_REPORT.
	 * This because all subqueries are UNIONED together.
	 *
	 * @todo   Uncomment 'AND order_address.deleted_at IS NULL' when
	 *         deletable address functionality is merged.
	 *
	 * @return Query
	 */
	public function getQueryBuilder()
	{
		$data = $this->_builderFactory->getQueryBuilder();

		$voids = $this->_builderFactory->getQueryBuilder()
			->select('transaction_record.record_id', true)
			->from('transaction')
			->joinUsing('transaction_record', 'transaction_id')
			->where('transaction_record.type = ?s', ['order'])
			->where('transaction.voided_at IS NOT NULL')
		;

		$data
			->select('item.created_at AS "Date"')
			->select('order_summary.currency_id AS "Currency"')
			->select('IFNULL(item.net, 0) AS "Net"')
			->select('IFNULL(item.tax, 0) AS "Tax"')
			->select('IFNULL(item.gross, 0) AS "Gross"')
			->select('order_summary.type AS "Source"')
			->select('"Order" AS "Type"')
			->select('item.item_id AS "Item_ID"')
			->select('item.order_id AS "Order_ID"')
			->select('NULL AS "Return_ID"')
			->select('item.product_id AS "Product_ID"')
			->select('product.name AS "Product"')
			->select('item.options AS "Option"')
			->select('country AS "Country"')
			->select('user.forename AS "User_Forename"')
			->select('user.surname AS "User_Surname"')
			->select('user.email AS "Email"')
			->select('order_summary.user_id AS "User_id"')
			->from('order_item AS item')
			->join('order_summary', 'item.order_id = order_summary.order_id')
			->leftJoin('order_address', 'order_summary.order_id = order_address.order_id AND order_address.type = "delivery"') //  AND order_address.deleted_at IS NULL
			->leftJoin('return_item', 'return_item.exchange_item_id = item.item_id')
			->leftJoin('user', 'order_summary.user_id = user.user_id')
			->leftJoin('product', 'item.product_id = product.product_id')
			->where('product.type != "voucher"')
			->where('order_summary.status_code >= 0')
			->where('order_summary.deleted_at IS NULL')
			->where('return_item.exchange_item_id IS NULL')
			->where('item.order_id NOT IN (?q)', [$voids]) // Exclude voided orders
		;

		// Filter dates
		if($this->_filters->exists('date_range')) {

			$dateFilter = $this->_filters->get('date_range');

			if($date = $dateFilter->getStartDate()) {
				$data->where('item.created_at > ?d', [$date->format('U')]);
			}

			if($date = $dateFilter->getEndDate()) {
				$data->where('item.created_at < ?d', [$date->format('U')]);
			}
		}

		// Filter currency
		if($this->_filters->exists('currency')) {
			$currency = $this->_filters->get('currency');
			if($currency = $currency->getChoices()) {
				is_array($currency) ?
					$data->where('order_summary.currency_id IN (?js)', [$currency]) :
					$data->where('order_summary.currency_id = (?s)', [$currency])
				;
			}
		}

		// Filter source
		if($this->_filters->exists('source')) {
			$source = $this->_filters->get('source');
			if($source = $source->getChoices()) {
				is_array($source) ?
					$data->where('order_summary.type IN (?js)', [$source]) :
					$data->where('order_summary.type = (?s)', [$source])
				;
			}
		}

		return $data;
	}
}