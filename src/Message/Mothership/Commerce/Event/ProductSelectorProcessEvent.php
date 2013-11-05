<?php

namespace Message\Mothership\Commerce\Event;

use Message\Mothership\Commerce\Product\Product;
use Message\Mothership\Commerce\Order\Entity\Item\Item;

use Message\Cog\Form\Handler as Form;

class ProductSelectorProcessEvent extends ProductSelectorEvent
{
	protected $_item;

	public function __construct(Form $form, Product $product, Item $item)
	{
		parent::__construct($form, $product);

		$this->_item = $item;
	}

	public function getItem()
	{
		return $this->_item;
	}
}