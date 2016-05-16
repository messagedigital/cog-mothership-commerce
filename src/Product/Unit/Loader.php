<?php

namespace Message\Mothership\Commerce\Product\Unit;

use Message\Mothership\Commerce\Product\Product;
use Message\Mothership\Commerce\Product\ProductEntityLoaderInterface;
use Message\Mothership\Commerce\Product\Loader as ProductLoader;
use Message\Cog\Localisation\Locale;
use Message\Cog\ValueObject\DateTimeImmutable;

use Message\Cog\DB\Entity\EntityLoaderCollection;
use Message\Cog\DB;
use Message\Cog\DB\Result;


class Loader implements ProductEntityLoaderInterface
{
	/**
	 * @var \Message\Cog\DB\QueryBuilderFactory
	 */
	protected $_queryBuilderFactory;

	/**
	 * @var \Message\Cog\DB\QueryBuilder
	 */
	protected $_queryBuilder;

	/**
	 * @var \Message\Cog\Localisation\Locale
	 */
	protected $_locale;

	/**
	 * @var \Message\Mothership\Commerce\Product\Loader
	 */
	protected $_productLoader;

	protected $_loadInvisible  = true;
	protected $_loadOutOfStock = false;
	protected $_loadDeleted = false;

	protected $_prices;
	protected $_defaultCurrency;

	protected $_returnArray = false;

	private $_entityLoaderCollection;

	public function __construct(DB\QueryBuilderFactory $queryBuilderFactory, Locale $locale, array $prices, $defaultCurrency)
	{
		$this->_defaultCurrency = $defaultCurrency;
		$this->_queryBuilderFactory   = $queryBuilderFactory;
		$this->_locale  = $locale;
		$this->_prices  = $prices;
		$this->_entityLoaderCollection = new EntityLoaderCollection();
	}

	public function setProductLoader(ProductLoader $loader)
	{
		// @todo this should be set to product loader's include deleted flag
		$loader->includeDeleted(true);
		$this->_productLoader = $loader;
		$this->_entityLoaderCollection->add('product', $this->_productLoader);
	}

	public function getDefaultCurrency()
	{
		return $this->_defaultCurrency;
	}

	public function getAll()
	{
		$this->_buildQuery();

		$this->_returnArray = true;

		return $this->_loadFromQuery();
	}

	/**
	 * Load all the units for a given Product object
	 *
	 * @param  Product $product get all units of this object
	 *
	 * @return array|false      Array of units, or false if no units exist
	 */
	public function getByProduct(Product $product)
	{
		$this->_buildQuery();

		$this->_returnArray = true;

		$this->_queryBuilder->where('product_unit.product_id = ?i', [$product->id]);

		return $this->_loadFromQuery($product);
	}

	public function getByID($unitID, $revisionID = null, Product $product = null)
	{
		if (!is_numeric($revisionID) && $revisionID !== null) {
			throw new \InvalidArgumentException('Revision ID must be numeric or null, '.gettype($revisionID).' given');
		}

		$this->_buildQuery($revisionID);

		$this->_returnArray = is_array($unitID);

		if (!is_array($unitID)) {
			$unitID = [$unitID];
		}

		$this->_queryBuilder->where('product_unit.unit_id IN (?ji)', [$unitID]);

		return $this->_loadFromQuery($product);
	}

	/**
	 * Get unit(s) by their barcode.
	 *
	 * Return value is always an array (an empty array of no `Unit`s found) if
	 * the `$barcode` parameter is passed as an array.
	 *
	 * @param  string|array $barcode Single barcode or array of barcodes
	 *
	 * @return array|Unit|false      Single unit or array of units, false or
	 *                               empty array if none found
	 */
	public function getByBarcode($barcode)
	{
		$this->_returnArray = is_array($barcode);

		if (!is_array($barcode)) {
			$barcode = [$barcode];
		}

		$this->_buildQuery();

		$this->_queryBuilder->where('product_unit.barcode IN (?js)', [$barcode]);

		return $this->_loadFromQuery();
	}

	/**
	 * Gets the sale units
	 * 
	 * @return array The units which are in sale
	 */
	public function getSaleUnits($currency = null)
	{
		if ($currency === null) {
			$currency = $this->_defaultCurrency;
		}

		$this->_returnArray = true;
		$this->_buildQuery();

		$units = $this->_loadFromQuery();

		foreach ($units as $key => $unit) {
			$rrp = $unit->getPrice('rrp', $currency);
			$retail = $unit->getPrice('retail', $currency);

			if ($retail >= $rrp) {
				unset($units[$key]);
			}
		}

		return $units;
	}

	public function includeInvisible($bool = true)
	{
		$this->_loadInvisible = $bool;

		return $this;
	}

	public function includeOutOfStock($bool = true)
	{
		$this->_loadOutOfStock = $bool;

		return $this;
	}

	public function includeDeleted($bool = true)
	{
		$this->_loadDeleted = $bool;

		return $this;
	}

	private function _buildQuery($revisionID = null)
	{
		$getRevision = $revisionID ?:
			$this->_queryBuilderFactory->getQueryBuilder()
				->select('MAX(revision_id)')
				->from('product_unit_info')
				->where('unit_id = product_unit.unit_id')
		;

		$this->_queryBuilder = $this->_queryBuilderFactory->getQueryBuilder()
			->select([
				// Unit info
				'product_unit.product_id    AS productID',
				'product_unit.unit_id      	AS id',
				'IFNULL(product_unit.weight_grams, product.weight_grams) AS weight',
				'product_unit_info.sku     	AS sku',
				'product_unit.barcode      	AS barcode',
				'product_unit.visible      	AS visible',
				'product_unit.created_at   	AS createdAt',
				'product_unit.created_by   	AS createdBy',
				'product_unit.updated_at   	AS updatedAt',
				'product_unit.updated_by   	AS updatedBy',
				'product_unit.deleted_at   	AS deletedAt',
				'product_unit.deleted_by   	AS deletedBy',
				'product_unit.supplier_ref 	AS supplierRef',
				'IFNULL(product_unit_info.revision_id,1) AS revisionID',
				'product_unit_info.sku      AS sku',

				// Stock
				'product_unit_stock.stock    AS stock',
				'product_unit_stock.location AS stockLocation',

				// Prices
				'product_price.type AS priceType',
				'product_price.currency_id AS currencyID',
				'IFNULL(product_unit_price.price, product_price.price) AS price',

				// Options
				'product_unit_option.option_name  AS optionName',
				'product_unit_option.option_value AS optionValue',
			])
			->from('product_unit')
			->join('product', 'product_unit.product_id = product.product_id') // Join product table to filter out units where product is hard deleted
			;

		if (is_numeric($getRevision)) {
			$this->_queryBuilder
				->leftJoin('product_unit_info', '
					product_unit_info.unit_id = product_unit.unit_id AND
					revision_id = :revisionID?i
				')
				->addParams(['revisionID' => $getRevision]);
			;
		} else {
			$this->_queryBuilder
				->leftJoin('product_unit_info', '
					product_unit_info.unit_id = product_unit.unit_id AND
					revision_id = (:revisionID?q)
				')
				->addParams(['revisionID' => $getRevision]);
		}

		$this->_queryBuilder
			->leftJoin('product_unit_stock', 'product_unit.unit_id = product_unit_stock.unit_id')
			->leftJoin('product_price', 'product_unit.product_id = product_price.product_id')
			->leftJoin('product_unit_price', '
				product_unit.unit_id = product_unit_price.unit_id AND
				product_price.type = product_unit_price.type AND
				product_price.currency_id = product_unit_price.currency_id
			')
			->leftJoin('product_unit_option', '
				product_unit_option.unit_id = product_unit.unit_id AND
				product_unit_option.revision_id = product_unit_info.revision_id
			')
		;

		if (!$this->_loadInvisible) {
			$this->_queryBuilder->where('product_unit.visible = ?b', [true]);
		}

		if (!$this->_loadOutOfStock) {
			$this->_queryBuilder->where('product_unit_stock.stock > 0');
		}

		if (!$this->_loadDeleted) {
			$this->_queryBuilder->where('product_unit.deleted_at IS NULL')
				->where('product.deleted_at IS NULL')
			;
		}
	}

	private function _loadFromQuery(Product $product = null)
	{
		if (null === $this->_queryBuilder) {
			throw new \LogicException('Cannot load from query as query has not been built yet');
		}

		$result = $this->_queryBuilder->run();

		$units = [];

		foreach ($result as $row) {
			if (!array_key_exists($row->id, $units)) {
				$unit = new UnitProxy(
					$this->_entityLoaderCollection,
					$this->_locale,
					$this->_prices,
					$this->_defaultCurrency
				);
				$unit->id          = $row->id;
				$unit->barcode     = $row->barcode;
				$unit->weight      = $row->weight;
				$unit->supplierRef = $row->supplierRef;
				$unit->revisionID  = $row->revisionID;
				$unit->options     = [];

				$unit->setSKU($row->sku);
				$unit->setVisible((bool) $row->visible);

				if ($product) {
					$unit->setProduct($product);
				} else {
					$unit->setProductID($row->productID);
				}

				$unit->authorship->create(new DateTimeImmutable(date('c', $row->createdAt)), $row->createdBy);

				if ($row->updatedAt) {
					$unit->authorship->update(new DateTimeImmutable(date('c', $row->updatedAt)), $row->updatedBy);
				}

				if ($row->deletedAt) {
					$unit->authorship->delete(new DateTimeImmutable(date('c', $row->deletedAt)), $row->deletedBy);
				}

				$units[$row->id] = $unit;
			}

			$unit = $units[$row->id];

			if ($row->optionName && $row->optionValue && !array_key_exists($row->optionName, $unit->options)) {
				$unit->options[$row->optionName] = $row->optionValue;
			}

			if (!array_key_exists($row->stockLocation, $unit->stock)) {
				$unit->stock[$row->stockLocation] = $row->stock;
			}

			$unit->setPrice($row->price, $row->priceType, $row->currencyID);
		}

		return $this->_returnArray ? $units : array_shift($units);
	}
}
