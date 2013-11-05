<?php

namespace Message\Mothership\Commerce\Controller\Module;

use Message\Mothership\Commerce\Events;
use Message\Mothership\Commerce\Event;
use Message\Mothership\Commerce\Product\Product;
use Message\Mothership\Commerce\Order;

use Message\Mothership\CMS\Page\Content;

use Message\Cog\Controller\Controller;

class ProductSelector extends Controller
{
	public function index(Product $product, array $options = null)
	{
		return $this->render('Message:Mothership:Commerce::product:product-selector', array(
			'product'  => $product,
			'form'     => $this->getForm($product, $options),
		));
	}

	public function getForm(Product $product, array $options = array())
	{
		$form = $this->get('form')
			->setName('select_product')
			->setAction($this->generateUrl('ms.commerce.product.add.basket', array('productID' => $product->id)))
			->setMethod('post');

		$choices = array();
		$options = array_filter($options);

		foreach ($product->getVisibleUnits() as $unit) {
			// Skip units that don't meet the options criteria, if set
			if ($options
			 && $options !== array_intersect_assoc($options, $unit->options)) {
				continue;
			}

			// Don't show option names that were passed as criteria to avoid weird-looking duplication
			$optionsToShow = ($options) ? array_diff_assoc($unit->options, $options) : $unit->options;

			$choices[$unit->id] = implode(', ', $optionsToShow);
		}

		// If there's only one unit available to choose, add it as a hidden field
		if (1 === count($choices)) {
			$form->add('unit_id', 'hidden', null, array(
				'attr' => array(
					'value' => key($choices),
				),
			));
		// Otherwise, add a select box to select the unit
		} else {
			$form->add('unit_id', 'choice', $this->trans('ms.commerce.product.selector.unit.label'), array(
				'choices' => $choices,
			));
		}

		$form = $this->get('event.dispatcher')->dispatch(
			Events::PRODUCT_SELECTOR_BUILD,
			new Event\ProductSelectorEvent($form, $product)
		)->getForm();

		return $form;
	}

	public function process($productID)
	{
		$product = $this->get('product.loader')->getByID($productID);
		$form    = $this->getForm($product);

		if ($form->isValid() && $data = $form->getFilteredData()) {
			$basket   = $this->get('basket');
			$unit     = $product->getUnit($data['unit_id']);
			$item     = new Order\Entity\Item\Item;

			$item->order         = $basket->getOrder();
			$item->stockLocation = $this->get('stock.locations')->get('web');
			$item->populate($unit);

			$item = $this->get('event.dispatcher')->dispatch(
				Events::PRODUCT_SELECTOR_PROCESS,
				new Event\ProductSelectorProcessEvent($form, $product, $item)
			)->getItem();

			if ($basket->addItem($item)) {
				$this->addFlash('success', 'The item has been added to your basket');
			}
		}

		return $this->redirectToReferer();
	}

}