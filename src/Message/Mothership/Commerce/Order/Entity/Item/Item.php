<?php

namespace Message\Mothership\Commerce\Order\Entity\Item;

use Message\Mothership\Commerce\Order\Entity\EntityInterface;
use Message\Mothership\Commerce\Product\Unit\Unit;
use Message\Mothership\Commerce\Order\Order;

use Message\Cog\Service\Container;
use Message\Cog\ValueObject\Authorship;

/**
 * Represents an item on an order.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Item implements EntityInterface
{
	public $id;

	public $order;
	public $authorship;
	public $status;

	public $listPrice       = 0;
	public $net             = 0;
	public $discount        = 0;
	public $tax             = 0;
	public $gross           = 0;
	public $rrp             = 0;
	public $taxRate         = 0;
	public $productTaxRate  = 0;
	public $taxStrategy;

	public $productID;
	public $productName;
	public $unitID;
	public $unitRevision;
	public $sku;
	public $barcode;
	public $options;
	public $brand;

	public $weight;
	public $stockLocation;

	public $personalisation;

	protected $_product;
	protected $_unit;

	public function __construct()
	{
		$this->personalisation = new Personalisation;
		$this->authorship      = new Authorship;

		$this->authorship
			->disableUpdate()
			->disableDelete();
	}

	public function __sleep()
	{
		$keys = array();
		foreach (get_object_vars($this) as $key => $value) {
			if (substr($key,0,1) == '_') {
				continue;
			}
			$keys[] = $key;
		}

		return $keys;
	}

	/**
	 * Populate this item with the data from a specific unit.
	 *
	 * @param  Unit   $unit The unit to populate from
	 *
	 * @return Item         Returns $this for chainability
	 */
	public function populate(Unit $unit)
	{
		if ($this->order instanceof Order) {
			$this->listPrice = $unit->getPrice('retail', $this->order->currencyID);
			$this->rrp       = $unit->getPrice('rrp', $this->order->currencyID);
		}

		$this->productTaxRate  = $unit->product->taxRate;
		$this->taxStrategy     = $unit->product->taxStrategy;
		$this->productID       = $unit->product->id;
		$this->productName     = $unit->product->name;
		$this->unitID          = $unit->id;
		$this->unitRevision    = $unit->revisionID;
		$this->sku             = $unit->sku;
		$this->barcode         = $unit->barcode;
		$this->options         = implode($unit->options, ', ');
		$this->brand           = $unit->product->brand;
		$this->weight          = $unit->weight;

		return $this;
	}

	/**
	 * Get the item description.
	 *
	 * The item description is made up of the brand name; the product name and
	 * the list of options. They are comma-separated, and if any of them are
	 * not set or blank they are excluded.
	 *
	 * @return string The item description
	 */
	public function getDescription()
	{
		return implode(', ', array_filter(array(
			$this->brand,
			$this->productName,
			$this->options,
		)));
	}

	/**
	 * Get the tax discount amount.
	 *
	 * If tax was charged for this item, `null` is always returned. Otherwise,
	 * the list price minus the discount minus the net amount is returned. This
	 * should equal the tax amount they would have paid if the order was taxable
	 * (that was therefore discounted).
	 *
	 * @return float|null The tax discount amount, or null if there was no tax
	 *                    discount
	 */
	public function getTaxDiscount()
	{
		if ($this->tax) {
			return null;
		}

		return round($this->listPrice - $this->discount - $this->net, 2);
	}

	/**
	 * Get the product associated with this order.
	 *
	 * The product is only loaded once per Item instance, unless `$reload` is
	 * passed as true.
	 *
	 * @todo Make this not access the service container statically!
	 *
	 * @param  boolean $reload True to force a reload of the Product instance
	 *
	 * @return \Message\Mothership\Commerce\Product\Product
	 */
	public function getProduct($reload = false)
	{
		if (!$this->_product || $reload) {
			$this->_product = Container::get('product.loader')->getByID($this->productID);
		}

		return $this->_product;
	}

	/**
	 * Get the unit associated with this order.
	 *
	 * The unit is loaded with the revision ID stored on this item, so the
	 * options should match.
	 *
	 * The unit is only loaded once per Item instance, unless `$reload` is
	 * passed as true.
	 *
	 * @todo Make this not access the service container statically!
	 *
	 * @param  boolean $reload True to force a reload of the Unit instance
	 *
	 * @return \Message\Mothership\Commerce\Product\Unit\Unit
	 */
	public function getUnit($reload = false)
	{
		if (!$this->_unit || $reload) {
			$this->_unit = Container::get('product.unit.loader')
				->includeInvisible(true)
				->includeOutOfStock(true)
				->getByID($this->unitID, $this->unitRevision);
		}

		return $this->_unit;
	}
}