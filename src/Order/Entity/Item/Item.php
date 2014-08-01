<?php

namespace Message\Mothership\Commerce\Order\Entity\Item;

use Message\Mothership\Commerce\Order\Entity\EntityInterface;
use Message\Mothership\Commerce\Product\Unit\Unit;
use Message\Mothership\Commerce\Order\Order;
use Message\Mothership\Commerce\Order\Transaction\RecordInterface;

use Message\Cog\Service\Container;
use Message\Cog\ValueObject\Authorship;

/**
 * Represents an item on an order.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Item implements EntityInterface, RecordInterface
{
	const RECORD_TYPE = 'item';

	public $id;

	public $order;
	public $authorship;
	public $status;

	public $listPrice      = 0; // Retail price of the item as advertised
	public $actualPrice    = 0; // Same as list price unless it was overriden
	public $basePrice      = 0; // Price of the item for this order (before discounts) (actual price with or without tax, as appropriate)
	public $net            = 0; // Net amount, calculated on discounted price
	public $discount       = 0; // Discount amount for this item
	public $tax            = 0; // Tax amount for this item
	public $gross          = 0; // Gross amount paid for this item (after discounts)
	public $rrp            = 0; // Recommended retail price of the item at time of purchase
	public $taxRate        = 0; // Tax rate for this item as used on this order
	public $productTaxRate = 0; // Tax rate of the product (regardless of tax actually being paid)
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
			->disableUpdate();
	}

	public function __sleep()
	{
		$keys = [];
		foreach (get_object_vars($this) as $key => $value) {
			if (substr($key, 0, 1) == '_') {
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

		$this->_product        = $unit->product;
		$this->productID       = $unit->product->id;
		$this->productName     = $unit->product->name;
		$this->brand           = $unit->product->brand;
		$this->productTaxRate  = (float) $unit->product->taxRate;
		$this->taxStrategy     = $unit->product->taxStrategy;

		$this->_unit            = $unit;
		$this->unitID          = $unit->id;
		$this->unitRevision    = $unit->revisionID;
		$this->sku             = $unit->sku;
		$this->barcode         = $unit->barcode;
		$this->options         = implode($unit->options, ', ');
		$this->weight          = (int) $unit->weight;

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

	public function getProduct($reload = false)
	{
		return $this->_product;
	}

	public function getUnit($reload = false)
	{
		return $this->_unit;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecordType()
	{
		return self::RECORD_TYPE;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecordID()
	{
		return $this->id;
	}
}