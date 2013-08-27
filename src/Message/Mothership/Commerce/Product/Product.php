<?php

namespace Message\Mothership\Commerce\Product;

use Message\Cog\Service\Container;
use Message\Cog\ValueObject\Authorship;
use Message\Cog\Localisation\Locale;

class Product
{
	public $id;
	public $catalogueID;
	public $brand;
	public $year;

	public $authorship;

	public $name;
	public $taxRate;
	public $taxStrategy;
	public $supplierRef;
	public $weight;

	public $displayName;
	public $season;
	public $description;
	public $category;
	public $fabric;
	public $features;
	public $careInstructions;
	public $shortDescription;
	public $sizing;
	public $notes;

	public $price 	= array();
	public $images  = array();
	public $tags    = array();

	public $exportDescription;
	public $exportValue;
	public $exportManufactureCountryID;

	public $priceTypes;

	protected $_entities = array();
	protected $_locale;


	/**
	 * Magic getter. This maps to defined order entities.
	 *
	 * @param  string $var       Entity name
	 *
	 * @return Entity\Collection The entity collection instance
	 *
	 * @throws \InvalidArgumentException If an entity with the given name doesn't exist
	 */
	public function __get($var)
	{
		if (!array_key_exists($var, $this->_entities)) {
			throw new \InvalidArgumentException(sprintf('Order entity `%s` does not exist', $var));
		}

		return $this->_entities[$var];
	}

	/**
	 * Magic isset. This maps to defined order entities.
	 *
	 * @param  string  $var Entity name
	 *
	 * @return boolean      True if the entity exist
	 */
	public function __isset($var)
	{
		return array_key_exists($var, $this->_entities);
	}

	/**
	 * Initiate the object and set some basic properties up
	 *
	 * @param Locale $locale     	Current locale instance
	 * @param array  $entities   	array of entities, this will proabbly only be units for now
	 * @param array  $priceTypes 	array of price types
	 */
	public function __construct(Locale $locale, array $entities = array(), array $priceTypes = array())
	{
		$this->authorship = new Authorship;
		$this->priceTypes = $priceTypes;
		$this->_locale    = $locale;

		foreach ($entities as $name => $loader) {
			$this->addEntity($name, $loader);
		}

		foreach ($priceTypes as $type) {
			$this->price[$type] = new Pricing($locale);
		}

	}

	/**
	 * Add an entity to this product.
	 *
	 * @param string                 $name   Entity name
	 * @param Entity\LoaderInterface $loader Entity loader
	 *
	 * @throws \InvalidArgumentException If an entity with the given name already exists
	 */
	public function addEntity($name, Unit\LoaderInterface $loader)
	{
		if (array_key_exists($name, $this->_entities)) {
			throw new \InvalidArgumentException(sprintf('Order entity already exists with name `%s`', $name));
		}

		$this->_entities[$name] = new Unit\Collection($this, $loader);
	}

	/**
	 * return units and give options as to which ones to display
	 *
	 * @param  boolean $showOutOfStock Bool to load out of stock units
	 * @param  boolean $showInvisible  Bool to load invisble units
	 *
	 * @return array                   array of Unit objects
	 */
	public function getUnits($showOutOfStock = true, $showInvisible = false) {
		$this->_entities['units']->load($this, $showOutOfStock, $showInvisible);
		return $this->_entities['units']->all();
	}

	/**
	 * Return an array of all units for this product, including out of stock and
	 * units set to invisble.
	 *
	 * @return array 		array of Unit objects
	 */
	public function getAllUnits()
	{
		return $this->getUnits(true, true);
	}

	public function getVisibleUnits()
	{
		return $this->getUnits(true, false);
	}

	/**
	 * Get a specfic unit by the unitID
	 *
	 * @param  int 		$unitID 	The unitID to load the Unit for
	 *
	 * @return Unit|false       	Loaded unit or false if not found
	 */
	public function getUnit($unitID)
	{
		try {
			return $this->_entities['units']->get($unitID);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Get the current price of price type based on the current locale and
	 * given currencyID
	 *
	 * @param  string $type       Price type to load
	 * @param  string $currencyID CurrencyID to load
	 *
	 * @return string             Loaded price
	 */
	public function getPrice($type = 'retail', $currencyID = 'GBP')
	{
		return $this->price[$type]->getPrice($currencyID, $this->_locale);
	}

	/**
	 * Check unit level prices and see whether there is any unit prices which are
	 * less that the product price
	 *
	 * @param  string $type       Type of price to check
	 * @param  string $currencyID CurrencyID of price to check
	 *
	 * @return string|false       Lowest price or false if $prices is empty
	 */
	public function getPriceFrom($type = 'retail', $currencyID = 'GBP')
	{
		$prices = array();
		foreach ($this->getAllUnits() as $unit) {
			if ($unit->getPrice($type, $currencyID) < $this->getPrice($type, $currencyID)) {
				$prices[$unit->getPrice($type, $currencyID)] = $unit->getPrice($type, $currencyID);
			}
		}
		// Sort the array with lowest value at the top
		ksort($prices);
		// get the lowest value
		return $prices ? array_shift($prices) : false;
	}

	/**
	 * Brand name doesn't work just yet
	 *
	 * @return [type] [description]
	 */
	public function getFullName() {
		return $this->brandName.', '.$this->displayName;
	}

	/**
	 * Return the internal product name (not the display name)
	 *
	 * @return string 	 product name
	 */
	public function getDefaultName()
	{
		return $this->name;
	}

	/**
	 * Return an image for a certain type and optional optionName nad optionValue
	 *
	 * @param  string  $type        Image type
	 * @param  string  $optionName  Optional option name
	 * @param  string  $optionValue Optional option value
	 *
	 * @return Image|false          Image object or false if it doesn't exist
	 */
	public function getImage($type = 'default', $optionName = null, $optionValue = null)
	{
		return $this->hasImage($type, $optionName, $optionValue);
	}

	/**
	 * Check that the image for a certain type, and options are available
	 *
	 * @param  string  		$type       The image type
	 *
	 * @return Image|false          	Image object or false if it doesn't exist
	 */
	public function hasImage($type = 'default', $optionName = null, $optionValue = null)
	{
		foreach ($this->images as $image) {
			if ($image->type == $type
			 && $optionName == $image->optionName
			 && $optionValue == $image->optionValue
			) {
				return $image;
			}
		}

		return false;
	}
}