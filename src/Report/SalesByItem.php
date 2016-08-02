<?php

namespace Message\Mothership\Commerce\Report;

use Message\Cog\DB\QueryBuilderInterface;
use Message\Cog\DB\QueryBuilderFactory;
use Message\Cog\Routing\UrlGenerator;
use Message\Cog\Event\DispatcherInterface;

use Message\Mothership\Report\Chart\TableChart;
use Message\Mothership\Report\Filter\DateRange;
use Message\Mothership\Report\Filter\Choices;

class SalesByItem extends AbstractSales
{
	/**
	 * Constructor.
	 *
	 * @param QueryBuilderFactory   $builderFactory
	 * @param UrlGenerator          $routingGenerator
	 * @param DispatcherInterface   $eventDispatcher
	 */
	public function __construct(
		QueryBuilderFactory $builderFactory,
		UrlGenerator $routingGenerator,
		DispatcherInterface $eventDispatcher,
		array $currencies
	)
	{
		parent::__construct($builderFactory, $routingGenerator, $eventDispatcher, $currencies);
		$this->_setName('sales_by_item');
		$this->_setDisplayName('Sales by Item');
		$this->_setReportGroup('Sales');
		$this->_setDescription('
			This report displays all individual items which save been sold/returned.
			By default it includes all data (orders, returns, shipping) from the last week (by completed date).
		');
		$startDate = new \DateTime;
		$this->getFilters()->get('date_range')->setStartDate($startDate->setTimestamp(strtotime(date('Y-m-d H:i')." -1 week")));
	}

	/**
	 * Set columns for use in reports.
	 *
	 * @return array  Returns array of columns as keys with format for Google Charts as the value.
	 */
	public function getColumns()
	{
		return [
			'Date'     => 'number',
			'Order'    => 'string',
			'Return'   => 'string',
			'Source'   => 'string',
			'Type'     => 'string',
			'Product'  => 'string',
			'Option'   => 'string',
			'Currency' => 'string',
			'Net'      => 'number',
			'Tax'      => 'number',
			'Gross'    => 'number',
		];
	}

	/**
	 * Dispatches event to get all sales, returns & shipping queries.
	 *
	 * Unions all sub queries & creates parent query.
	 * No grouping or summing, displays all items.
	 * Order by DATE.
	 *
	 * @return Query
	 */
	protected function _getQuery()
	{
		$fromQuery = $this->_getFilteredQuery();

		$queryBuilder = $this->_builderFactory->getQueryBuilder();
		$queryBuilder
			->select('date AS "UnixDate"')
			->select('FROM_UNIXTIME(date, "%d-%b-%Y %H:%m") AS "Date"')
			->select('totals.order_id AS "Order"')
			->select('totals.return_id AS "Return"')
			->select('totals.item_id AS "Item"')
			->select('totals.source AS "Source"')
			->select('totals.type AS "Type"')
			->select('totals.product_id AS "Product_ID"')
			->select('totals.product AS "Product"')
			->select('totals.option AS "Option"')
			->select('totals.currency AS "Currency"')
			->select('totals.net AS "Net"')
			->select('totals.tax AS "Tax"')
			->select('totals.gross AS "Gross"')
			->from('totals', $fromQuery)
			->orderBy('UnixDate DESC')
		;

		// Filter type
		if($this->_filters->exists('type')) {
			$type = $this->_filters->get('type');
			if($type = $type->getChoices()) {
				is_array($type) ?
					$queryBuilder->where('Type IN (?js)', [$type]) :
					$queryBuilder->where('Type = (?s)', [$type])
				;
			}
		}

		return $queryBuilder->getQuery();
	}

	/**
	 * Takes the data and transforms it into a useable format.
	 *
	 * @param  $data    DB\Result    The data from the report query.
	 * @param  $output  string|null  The type of output required.
	 *
	 * @return string|array  Returns data as string in JSON format or array.
	 */
	protected function _dataTransform($data, $output = null)
	{
		$result = [];

		if ($output === "json") {
			foreach ($data as $row) {
				$result[] = [
					[
						'v' => (float) $row->UnixDate,
						'f' => (string) $row->Date
					],
					'<a href ="'.$this->generateUrl('ms.commerce.order.detail.view', ['orderID' => (int) $row->Order]).'">'.$row->Order.'</a>',
					$row->Return ?
						'<a href ="'.$this->generateUrl('ms.commerce.return.view', ['returnID' => (int) $row->Return]).'">'.$row->Return.'</a>'
						: "",
					ucwords($row->Source),
					ucwords($row->Type),
					$row->Product_ID ?
						[
							'v' => ucwords($row->Product),
							'f' => (string) '<a href ="'.$this->generateUrl('ms.commerce.product.edit.attributes', ['productID' => $row->Product_ID]).'">'
							.ucwords($row->Product).'</a>'
						]
						: $row->Product,
					ucwords($row->Option),
					$row->Currency,
					[
						'v' => (float) $row->Net,
						'f' => (string) number_format($row->Net,2,'.',',')
					],
					[
						'v' => (float) $row->Tax,
						'f' => (string) number_format($row->Tax,2,'.',',')
					],
					[
						'v' => (float) $row->Gross,
						'f' => (string) number_format($row->Gross,2,'.',',')
					],
				];
			}

			return json_encode($result);
		} else {
			foreach ($data as $row) {
				$result[] = [
					$row->Date,
					$row->Order,
					$row->Return ? $row->Return : "",
					ucwords($row->Source),
					ucwords($row->Type),
					$row->Product_ID ? $row->Product : "",
					ucwords($row->Option),
					$row->Currency,
					$row->Net,
					$row->Tax,
					$row->Gross,
				];
			}

			return $result;
		}
	}
}