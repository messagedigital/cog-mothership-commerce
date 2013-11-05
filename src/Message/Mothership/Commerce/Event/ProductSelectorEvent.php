<?php

namespace Message\Mothership\Commerce\Event;

use Message\Mothership\Commerce\Product\Product;

use Message\Cog\Event\Event as BaseEvent;
use Message\Cog\Form\Handler as Form;

class ProductSelectorEvent extends BaseEvent
{
	protected $_form;
	protected $_product;

	public function __construct(Form $form, Product $product)
	{
		$this->_form    = $form;
		$this->_product = $product;
	}

	public function getForm()
	{
		return $this->_form;
	}

	public function getProduct()
	{
		return $this->_product;
	}
}