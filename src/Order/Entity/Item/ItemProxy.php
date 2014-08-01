<?php

namespace Message\Mothership\Commerce\Order\Entity\Item;

use Message\Cog\DB\Entity\EntityLoaderCollection;

/**
 * Lazy Loading Proxy for Items.
 *
 * @author Iris Schaffer <iris@message.co.uk>
 */
class ItemProxy extends Item
{
	protected $_loaders;
	protected $_loaded = [];

	public function __construct(EntityLoaderCollection $loaders)
	{
		$this->_loaders = $loaders;
		
		parent::__construct();
	}

	/**
	 * Get the product associated with this order.
	 *
	 * The product is only loaded once per Item instance, unless `$reload` is
	 * passed as true.
	 *
	 * @param  boolean $reload True to force a reload of the Product instance
	 *
	 * @return \Message\Mothership\Commerce\Product\Product
	 */
	public function getProduct($reload = false)
	{
		$this->_loadByID('product', $this->productID, $reload);

		return parent::getProduct($reload);
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
	 * @param  boolean $reload True to force a reload of the Unit instance
	 *
	 * @return \Message\Mothership\Commerce\Product\Unit\Unit
	 */
	public function getUnit($reload = false)
	{
		if (!$reload && null !== $this->_unit) {
			return;
		}

		$this->_unit = $this->_loaders->get('unit')
			->includeOutOfStock(true)
			->includeInvisible(true)
			->loadByID($this->unitID, $this->unitRevision);

		$this->_product = $this->_unit->product;

		$this->_loaded[] = 'unit';
		$this->_loaded[] = 'product';

		return parent::getUnit($reload);
	}

	protected function _loadByID($entityName, $id, $reload = false)
	{
		if (!$reload && null !== $this->{'_' . $entityName}) {
			return;
		}

		$entities = $this->_loaders->get($entityName)->getById($id);
		
		if ($entity !== false) {
		 	$this->{'_' . $entityName} = $entity;
		}

		$this->_loaded[] = $entityName;
	}
}