<?php

namespace Message\Mothership\Commerce\Order;

use Message\Cog\Service\Container;
use Message\Cog\ValueObject\Authorship;
use Message\Mothership\Commerce\Payable\PayableInterface;

/**
 * Order model. Container for all information about an order.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */

class Order implements PayableInterface, Transaction\RecordInterface
{
	const RECORD_TYPE = 'order';

	public $id;
	public $orderID; // alias of $id for BC

	public $user;
	public $userEmail;
	public $authorship;
	public $status;

	public $type;
	public $locale;
	public $taxable;
	public $currencyID;
	public $conversionRate    = 0;

	public $productNet        = 0;
	public $productDiscount   = 0;
	public $productTax        = 0;
	public $productGross      = 0;

	public $totalNet          = 0;
	public $totalDiscount     = 0;
	public $totalTax          = 0;
	public $totalGross        = 0;

	public $shippingName;
	public $shippingDisplayName;
	public $shippingListPrice = 0;
	public $shippingNet       = 0;
	public $shippingDiscount  = 0;
	public $shippingTax       = 0;
	public $shippingTaxRate   = 0;
	public $shippingGross     = 0;

	private $_shippingTaxes = [];

	public $metadata;

	protected $_payableTransactionID;

	protected $_entities = array();

	/**
	 * Constructor.
	 *
	 * @param array $entities An array of order entities to use, where the key
	 *                        is the entity name and the value is the loader
	 */
	public function __construct(array $entities = array())
	{
		$this->authorship = new Authorship;
		$this->metadata   = new Metadata;

		foreach ($entities as $name => $collection) {
			$this->addEntity($name, $collection);
		}
	}

	/**
	 * Magic getter. This maps to defined order entities.
	 *
	 * @see _getEntity
	 *
	 * @param  string $var       Entity name
	 *
	 * @return Entity\Collection The entity collection instance
	 */
	public function __get($var)
	{
		return $this->_getEntity($var);
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
	 * Add an entity to this order.
	 *
	 * @param string                 $name       Entity name
	 * @param Entity\OrderCollection $collection Entity order collection
	 *
	 * @throws \InvalidArgumentException If an entity with the given name already exists
	 */
	public function addEntity($name, Entity\CollectionOrderLoader $collection)
	{
		if (array_key_exists($name, $this->_entities)) {
			throw new \InvalidArgumentException(sprintf('Order entity already exists with name `%s`', $name));
		}

		// $collection = clone $collection;
		$collection->setOrder($this);

		$this->_entities[$name] = $collection;
	}

	/**
	 * Get an array of all entities set on this order, where the value is the
	 * instance of `Entity\Collection`.
	 *
	 * @return array Array of entities
	 */
	public function getEntities()
	{
		return $this->_entities;
	}

	/**
	 * Get the items for this order.
	 *
	 * @deprecated Access the "items" property directly instead
	 *
	 * @param  mixed $filter                 Item ID if you want to get a
	 *                                       specific item
	 *
	 * @return Entity\Collection|Entity\Item Collection of all items, or a
	 *                                       specific item
	 */
	public function getItems($id = null)
	{
		if ($id) {
			return $this->_getEntity('items')->get($id);
		}

		return $this->_getEntity('items');
	}

	/**
	 * Get an array of item "rows". Rows are all items for a specific unit
	 * grouped.
	 *
	 * This is mostly used for listings.
	 *
	 * @see Entity\Item\Row
	 *
	 * @return array[Entity\Item\Row] Array of item rows
	 */
	public function getItemRows()
	{
		return $this->_getEntity('items')->getRows();
	}

	/**
	 * Get the address for this order of a specific type.
	 *
	 * @param  string $type                 The address type
	 *
	 * @return Entity\Address\Address|false The address, or false if it was not
	 *                                      found
	 *
	 * @throws \UnexpectedValueException If more than one address of this type
	 *                                   was found
	 */
	public function getAddress($type)
	{
		return $this->_getEntity('addresses')->getByType($type);
	}

	/**
	 * Get the country ID for the address for this order of a specific type.
	 *
	 * @deprecated Access the address using the "addresses" property instead
	 *
	 * @see getAddress
	 *
	 * @param  string $type                 The address type
	 *
	 * @return Entity\Address\Address|false The address, or false if it was not
	 *                                      found
	 */
	public function getCountryID($type)
	{
		$address = $this->getAddress($type);

		return $address ? $address->countryID : false;
	}

	/**
	 * Get the discounts associated with this order.
	 *
	 * @deprecated Just use the discounts property directly instead
	 *
	 * @return Entity\Collection Collection of discounts
	 */
	public function getDiscounts()
	{
		return $this->_getEntity('discounts');
	}

	/**
	 * Get the dispatches associated with this order.
	 *
	 * @deprecated Just use the dispatches property directly instead
	 *
	 * @param int|null $id       ID of the dispatch to get, or null to get them
	 *                           all
	 *
	 * @return Entity\Collection Collection of dispatches
	 */
	public function getDespatches($id = null)
	{
		if ($id) {
			return $this->_getEntity('dispatches')->get($id);
		}

		return $this->_getEntity('dispatches');
	}

	/**
	 * Get the payments associated with this order.
	 *
	 * @deprecated Just use the payments property directly instead
	 *
	 * @param int|null $id       ID of the payment to get, or null to get them
	 *                           all
	 *
	 * @return Entity\Collection Collection of payments
	 */
	public function getPayments($id = null)
	{
		if ($id) {
			return $this->_getEntity('payments')->get($id);
		}

		return $this->_getEntity('payments');
	}

	/**
	 * Get the refunds associated with this order.
	 *
	 * @deprecated Just use the refunds property directly instead
	 *
	 * @param int|null $id       ID of the refund to get, or null to get them
	 *                           all
	 *
	 * @return Entity\Collection Collection of refunds
	 */
	public function getRefunds($id = null)
	{
		if ($id) {
			return $this->_getEntity('refunds')->get($id);
		}

		return $this->_getEntity('refunds');
	}

	/**
	 * Get the returns associated with this order.
	 *
	 * @deprecated Just use the returns property directly instead
	 *
	 * @param int|null $id       ID of the return to get, or null to get them
	 *                           all
	 *
	 * @return Entity\Collection Collection of returns
	 */
	public function getReturns($id = null)
	{
		if ($id) {
			return $this->_getEntity('returns')->get($id);
		}

		return $this->_getEntity('returns');
	}

	/**
	 * Get the notes associated with this order.
	 *
	 * @deprecated Just use the notes property directly instead
	 *
	 * @param int|null $id       ID of the note to get, or null to get them
	 *                           all
	 *
	 * @return Entity\Collection Collection of notes
	 */
	public function getNotes($raisedFrom = false)
	{
		if ($raisedFrom) {
			return $this->_getEntity('notes')->getByProperty('raisedFrom', $raisedFrom);
		}

		return $this->_getEntity('notes');
	}

	/**
	 * Get the notes associated with this order that are visible to the
	 * customer.
	 *
	 * @return Entity\Collection Collection of notes
	 */
	public function getCustomerNotes()
	{
		return $this->_getEntity('notes')->getByProperty('customerNotified', 1);
	}

	/**
	 * Get the grand total for this order.
	 *
	 * @deprecated Just use the totalGross property directly
	 *
	 * @return float The grand total amount
	 */
	public function getTotal()
	{
		return $this->totalGross;
	}

	/**
	 * Get the "simple" currency ID for this order (the currency ID without the
	 * locale ID).
	 *
	 * This is still here for backwards-compatibility and is due to be removed
	 * in a future version.
	 *
	 * @deprecated Just use the currencyID property directly instead
	 *
	 * @return string The currency ID
	 */
	public function getSimpleCurrencyID()
	{
		return $this->currencyID;
	}

	/**
	 * Get the metadata for this order.
	 *
	 * This is still here for backwards-compatibility and is due to be removed
	 * in a future version.
	 *
	 * @deprecated Just use the metadata property directly instead
	 *
	 * @return Metadata
	 */
	public function getMetadata()
	{
		return $this->metadata;
	}

	/**
	 * Get a specific entity collection.
	 *
	 * @param  string $var       Entity name
	 *
	 * @return Entity\Collection The entity collection instance
	 *
	 * @throws \InvalidArgumentException If an entity with the given name doesn't exist
	 */
	protected function _getEntity($name)
	{
		if (!array_key_exists($name, $this->_entities)) {
			throw new \InvalidArgumentException(sprintf('Order entity `%s` does not exist', $name));
		}

		return $this->_entities[$name];
	}

	/**
	 * returns amount left to pay after current payments on the order have taken from the total
	 *
	 * @return float total left to pay
	 */
	public function getAmountDue()
	{
		$total = $this->totalGross;
		foreach ($this->payments as $payment) {
			$total -= $payment->amount;
		}

		return $total;
	}

	/**
	 * Get the sum of the payment amounts on the order.
	 *
	 * @return float
	 */
	public function getAmountPaid()
	{
		$paid = 0;

		foreach ($this->payments as $payment) {
			$paid += $payment->amount;
		}

		return $paid;
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

	/**
	 * {@inheritDoc}
	 */
	public function getPayableAmount()
	{
		return $this->getAmountDue();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayableCurrency()
	{
		return $this->currencyID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayableAddress($type)
	{
		return $this->getAddress($type);
	}

	/**
	 * Retrieves the payableTransactionID property, this is set once to ensure
	 * it remains the same between requests.
	 *
	 * {@inheritDoc}
	 */
	public function getPayableTransactionID()
	{
		if (! $this->_payableTransactionID) {
			$this->_payableTransactionID = 'ORDER-' . strtoupper(uniqid());
		}

		return $this->_payableTransactionID;

	}

	/**
	 * Updates the totals
	 * 
	 * @return $this
	 */
	public function updateTotals()
	{
		$this->productNet      = 0;
		$this->productDiscount = 0;
		$this->productTax      = 0;
		$this->productGross    = 0;

		foreach ($this->items as $item) {
			$this->productNet      += $item->net;
			$this->productDiscount += $item->discount;
			$this->productTax      += $item->getTax();
			$this->productGross    += $item->gross;
		}

		$this->totalNet        = $this->productNet      + $this->shippingNet;
		$this->totalDiscount   = $this->productDiscount + $this->shippingDiscount;
		$this->totalTax        = $this->productTax      + $this->shippingTax;
		$this->totalGross      = $this->productGross    + $this->shippingGross;

		return $this;
	}

    /**
     * Gets the shipping taxes.
     *
     * @return mixed
     */
    public function getShippingTaxes()
    {
        return $this->_shippingTaxes;
    }

    /**
     * Sets the shipping taxes as well as the total tax rate.
     *
     * @param mixed $_shippingTaxes the shipping taxes
     *
     * @return self
     */
    public function setShippingTaxes($shippingTaxes)
    {
        $this->_shippingTaxes = $shippingTaxes;
        $this->shippingTaxRate = 0;

        foreach($shippingTaxes as $rate) {
        	$this->shippingTaxRate += $rate;
        }

        $this->shippingGross = round($this->shippingListPrice - $this->shippingDiscount, 2);
		$this->shippingTax   = round(($this->shippingGross / (100 + $this->shippingTaxRate)) * $this->shippingTaxRate, 2);
		$this->shippingNet   = round($this->shippingGross - $this->shippingTax, 2);

        return $this;
    }

	/**
	 * Gets the gross price of the items
	 * 
	 * @return float the gross total
	 */
	public function getProductGross()
	{
		$total = (float) 0;
		foreach ($this->getItems() as $item) {
			$total += $item->gross;
		}

		return $total;
	}

	/**
	 * Gets the net price of the items
	 * 
	 * @return float the net total
	 */
	public function getProductNet()
	{
		$total = (float) 0;
		foreach ($this->getItems() as $item) {
			$total += $item->net;
		}

		return $total;
	}

	/**
	 * Gets the gross price of the order
	 * 
	 * @return float the gross total
	 */
	public function getTotalGross()
	{
		$total = $this->getProductGross() + $this->shippingGross - $this->totalDiscount;

		return $total;
	}

	/**
	 * Gets the net price of the order
	 * 
	 * @return float the net total
	 */
	public function getTotalNet()
	{
		$total = $this->getProductNet() + $this->shippingNet - $this->totalDiscount;

		return $total;
	}
}