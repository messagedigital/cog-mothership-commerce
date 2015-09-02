<?php

namespace Message\Mothership\Commerce\Product;

use Message\Cog\Localisation\Locale;
use Message\Cog\DB\Entity\EntityLoaderCollection;
use Message\Mothership\Commerce\Product\Tax\Strategy\TaxStrategyInterface;

class ProductProxy extends Product
{
	protected $_loaders;
	protected $_loaded = [];

	public function __construct(
		Locale $locale,
		array $priceTypes = array(),
		$defaultCurrency,
		EntityLoaderCollection $loaders,
		TaxStrategyInterface $taxStrategy
	) {
		parent::__construct($locale, $priceTypes, $defaultCurrency, $taxStrategy);

		$this->_loaders = $loaders;
	}

	/**
	 * Exclude lazy loaded attributes
	 * 
	 * @{inheritDocs}
	 */
	public function __sleep()
	{
		return array_diff(array_keys(get_object_vars($this)), [
			'_units',
			'_images',
			'_details',
			'_taxes',
			'_prices',
			'_loaded'
		]);
	}

	/**
	 * Reinitialize lazily loaded attributes
	 * 
	 * @{inheritDocs}
	 */
	public function __wakeup()
	{
		$this->_loaded = [];

		$this->_units      = new Unit\Collection;
		$this->_images     = new Image\Collection;
		$this->_details    = new Type\DetailCollection;
		$this->_taxes      = new Tax\Rate\TaxRateCollection;
		$this->_prices     = new Price\PriceCollection;
	}

	public function getAllUnits()
	{
		if (!in_array('units', $this->_loaded)) {
			$this->_loadUnits();
		}

		return parent::getAllUnits();
	}

	public function getVisibleUnits()
	{
		if (!in_array('units', $this->_loaded)) {
			$this->_loadUnits();
		}

		return parent::getVisibleUnits();
	}

	public function getUnitCollection()
	{
		if (!in_array('units', $this->_loaded)) {
			$this->_loadUnits();
		}

		return parent::getUnitCollection();
	}

	public function getUnits($showOutOfStock = true, $showInvisible = false)
	{
		if (!in_array('units', $this->_loaded)) {
			$this->_loadUnits();
		}

		return parent::getUnits($showOutOfStock, $showInvisible);
	}

	public function getUnit($unitID)
	{
		if (!in_array('units', $this->_loaded)) {
			$this->_loadUnits();
		}

		return parent::getUnit($unitID);
	}

	public function getImages($type = 'default', array $options = null)
	{
		$this->_load('images');

		return parent::getImages($type, $options);
	}

	public function getDetails()
	{
		$this->_load('details');

		return parent::getDetails();
	}

	public function getPrices()
	{
		$this->_load('prices');

		return parent::getPrices();
	}

	public function addUnit(Unit\Unit $unit)
	{
		if (!in_array('units', $this->_loaded)) {
			return $this->_loaders->get('units')->getByID($unit->id);
		}

		return parent::addUnit($unit);
	}

	public function getTaxRates()
	{
		$this->_load('taxes');

		return parent::getTaxRates();
	}

	private function _loadUnits()
	{
		$this->_loaders->get('units')
			->includeOutOfStock(true)
			->includeInvisible(true);

		$this->_load('units');
	}

	protected function _load($entityName)
	{
		if (in_array($entityName, $this->_loaded)) {
			return;
		}

		$entities = $this->_loaders->get($entityName)->getByProduct($this);
		if ($entities !== false) {
			foreach ($entities as $entity) {
				$this->{'_' . $entityName}->add($entity);
			}
		}

		$this->_loaded[] = $entityName;
	}
}