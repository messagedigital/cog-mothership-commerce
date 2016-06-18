<?php

namespace Message\Mothership\Commerce\Controller\Product;

use Message\Mothership\Commerce\Product\Product;
use Message\Mothership\Commerce\Product\Image;
use Message\Mothership\Commerce\Product\Stock;
use Message\Mothership\Commerce\Product\Stock\Movement\Reason\Reason;
use Message\Mothership\Commerce\Field;
use Message\Mothership\Commerce\Product\Barcode\CodeGenerator\Exception\BarcodeGenerationException;

use Message\Mothership\FileManager\File;

use Message\Cog\Controller\Controller;
use Message\Cog\ValueObject\DateTimeImmutable;

class Edit extends Controller
{
	const HIDDEN_SUFFIX = '_hidden';
	const UNIT_DATA = 'ms.commerce.unit.data';

	/**
	 * @var Product
	 */
	protected $_product;
	protected $_units = array();

	/**
	 * Show product edit screen
	 *
	 * @param  int 		$productID  ProductID to load
	 *
	 * @return Response 			Output for view
	 */
	public function productAttributes($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);

		return $this->render('::product:edit-form', array(
			'product' => $this->_product,
			'form'    => $this->_getProductAttributesForm(),
		));
	}

	public function productDetails($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);

		return $this->render('::product:edit-form', array(
			'product' => $this->_product,
			'form'    => $this->_getProductDetailsForm(),
		));
	}

	public function productPricing($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);

		return $this->render('::product:edit-pricing', array(
			'product' => $this->_product,
			'form'    => $this->_getProductPricingForm(),
		));
	}

	/**
	 * Show product units with form for editing them
	 *
	 * @param  int 		$productID 	ProductID to load
	 */
	public function units($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);
		$this->_units 	= $this->_product->getAllUnits();

		return $this->render('::product:edit-unit', array(
			'headings'    => $this->_getAllOptionsAndHeadings(),
			'locale'      => $this->get('locale'),
			'product'     => $this->_product,
			'units'       => $this->_units ,
			'form'        => $this->_getUnitForm(),
			'barcodeForm' => $this->createForm($this->get('product.form.unit.barcode'), null, [
				'action' => $this->get('routing.generator')->generate('ms.commerce.product.unit.barcode', ['productID' => $this->_product->id]),
			]),
			'addForm'     => $this->_addNewUnitForm(),
			'optionValue' => $this->get('option.loader')->getAllOptionValues(),
		));
	}

	public function deleteUnit($unitID)
	{
		$unit = $this->get('product.unit.loader')
			->includeInvisible(true)
			->includeOutOfStock(true)
			->getByID((int) $unitID);

		if ($unit) {
			$this->get('product.unit.delete')->delete($unit);
			$this->addFlash('success','Unit deleted');
		}

		return $this->redirectToReferer();
	}

	/**
	 * Method for unit stock interface
	 *
	 * @param  int 	$productID 	ProductID to load
	 */
	public function stock($productID)
	{
		$locations = $this->get('stock.locations')->all();
		$this->_product = $this->get('product.loader')->getByID($productID);
		$this->_units = $this->_product->getAllUnits();

		$movementIterator = $this->get('stock.movement.iterator');
		$movementIterator->addUnits($this->_product->getAllUnits());

		return $this->render('::product:edit-stock', array(
			'headings'  	   => $this->_getAllOptionsAndHeadings(),
			'locale'  		   => $this->get('locale'),
			'product' 		   => $this->_product,
			'units'   		   => $this->_units,
			'locations' 	   => $locations,
			'form'	  		   => $this->_getStockForm($locations),
			'movementIterator' => $movementIterator,
		));
	}

	public function images($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);
		$images = [];
		$types  = $this->get('product.image.types');

		foreach ($this->_product->getImages() as $image) {
			$label = $types->get($image->type);

			if (!array_key_exists($label, $images)) {
				$images[$label] = [];
			}

			$images[$label][] = $image;
		}

		return $this->render('::product:edit-images', array(
			'locale'  => $this->get('locale'),
			'product' => $this->_product,
			'form'	  => $this->_getImageForm(),
			'images'  => $images,
		));
	}

	public function deleteImage($imageID)
	{
		$form = $this->createForm($this->get('product.image.form.delete'));
		$form->handleRequest();

		if ($form->isValid()) {
			$this->get('product.image.delete')->delete($this->get('product.image.loader')->getByID($imageID));

			$this->addFlash('success', 'Image deleted successfully');
		}

		return $this->redirectToReferer();
	}

	public function deleteImageForm($imageID)
	{
		$form = $this->createForm($this->get('product.image.form.delete'), null, [
			'action' => $this->get('routing.generator')
				->generate('ms.commerce.product.delete.images', ['imageID' => $imageID,]),
			'method' => 'delete',
		]);

		return $this->render('Message:Mothership:Commerce::product:image:delete-form', ['form' => $form]);
	}

	public function barcodeAction($productID)
	{
		$form = $this->createForm($this->get('product.form.unit.barcode'));
		$form->handleRequest();

		if ($form->isValid() && $data = $form->getData()) {
			$unit = $this->get('product.unit.loader')
				->includeInvisible()
				->includeOutOfStock()
				->getByID((int) $data['unit'])
			;

			$unit->barcode = $data['barcode'];

			$this->get('product.barcode.edit')->save($unit);
		}

		return $this->redirectToReferer();
	}

	/**
	 * Process the updating of the units data
	 *
	 * @param  int 		$productID ProductID to load
	 */
	public function processUnit($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);
		$this->_units   = $this->_product->getAllUnits();
		$form           = $this->_getUnitForm();
		$form->handleRequest();

		if ($form->isValid() && $data = $form->getData()) {

			foreach ($data as $unitID => $values) {
				// original unit
				$unit = clone $this->_units[$unitID];
				// unit to update
				$changedUnit = clone $unit;

				$changedUnit->sku 		= $values['sku'];

				$changedUnit->weight 	= (null === $values['weight'] ? null : (int) $values['weight']);
				$changedUnit->visible 	= (int) (bool) $values['visible'];

				foreach ($values['prices']['currencies'] as $currency => $currPrices) {
					foreach($currPrices as $type => $price) {
						$changedUnit->price[$type]->setPrice($currency, $price, $this->get('locale'));
					}
				}

				foreach ($values['options'] as $type => $value) {
					$changedUnit->options[$type] = $value;
				}

				if ($changedUnit != $unit) {
					$changedUnit = $this->get('product.unit.edit')->save($changedUnit);
				}
				if ($changedUnit->price !== $unit->price) {
					$changedUnit = $this->get('product.unit.edit')->savePrices($changedUnit);
				}
			}
		}

		return $this->redirectToRoute('ms.commerce.product.edit.units', array('productID' => $this->_product->id));
	}

	public function processAddUnit($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);
		$form = $this->_addNewUnitForm();
		$form->handleRequest();
		$data = $form->getData();

		if ($form->isValid() && $data) {
			$unit              = $this->get('product.unit');
			$unit->sku         = $data['sku'];
			$unit->weight 	   = $data['weight'];
			$unit->revisionID  = 1;
			$unit->product     = $this->_product;

			foreach ($data['prices']['currencies'] as $currency => $types) {
				foreach ($types as $type => $value) {
					$unit->price[$type]->setPrice($currency, $value, $this->get('locale'));
				}
			}

			$unit->authorship->create(new DateTimeImmutable, $this->get('user.current'));

			foreach($data['options'] as $optionArray) {
				$unit->setOption($optionArray['name'], $optionArray['value']);
			}

			try {
				$unit = $this->get('product.unit.create')->create($unit);
			} catch (BarcodeGenerationException $e) {
				$this->addFlash('warning', 'ms.commerce.product.barcode.create.error', [
					'%sku%' => $unit->sku,
				]);
			}

			$this->addFlash('success', 'ms.commerce.product.units.create.success', [
				'%sku%' => $unit->sku,
			]);
		} else {
			$this->get('http.session')->set(self::UNIT_DATA, $data);
		}

		return $this->redirectToRoute('ms.commerce.product.edit.units', array('productID' => $this->_product->id));
	}

	public function processProductAttributes($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);

		$form = $this->_getProductAttributesForm();
		if ($form->isValid() && $data = $form->getFilteredData()) {
			$product = $this->_product;
			$trans   = $this->get('db.transaction');

			$productEdit = $this->get('product.edit');
			$productEdit->setTransaction($trans);

			$product->name                        = $data['name'];
			$product->shortDescription            = $data['short_description'];
			$product->displayName                 = $data['display_name'];
			$product->sortName                    = $data['sort_name'];
			$product->description                 = $data['description'];
			$product->category                    = $data['category'];
			$product->brand                       = $data['brand'];
			$product->exportDescription           = $data['export_description'];
			$product->tags                        = $data['tags'];
			$product->supplierRef                 = $data['supplier_ref'];
			$product->notes                       = $data['notes'];
			$product->weight                      = $data['weight_grams'];
			$product->exportManufactureCountryID  = $data['export_manufacture_country_id'];
			$product->type                        = $this->get('product.types')->get($data['product_type']);
			$product->setExportCode($data['export_code']);

			$product = $productEdit->save($product);
			$product = $productEdit->saveTags($product);
			$trans->commit();

			if ($product->id) {
				$this->addFlash('success', 'Product updated successfully');
				return $this->redirectToReferer();
			} else {
				$this->addFlash('error', 'Unable to updated product');
			}
		}

		return $this->render('::product:edit-form', array(
			'product' => $this->_product,
			'form'    => $form,
		));
	}

	public function processProductDetails($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);

		$form = $this->_getProductDetailsForm();
		$form->handleRequest();

		if ($form->isValid() && $data = $form->getData()) {
			$product = $this->_product;
			$trans   = $this->get('db.transaction');

			$detailEdit = $this->get('product.detail.edit');
			$detailEdit->setTransaction($trans);

			$productEdit = $this->get('product.edit');
			$productEdit->setTransaction($trans);

			$product->setDetails($detailEdit->updateDetails($data, $product->getDetails()));
			$detailEdit->save($product);

			$product = $productEdit->save($product);

			$trans->commit();

			if ($product->id) {
				$this->addFlash('success', 'Product updated successfully');
				return $this->redirectToReferer();
			} else {
				$this->addFlash('error', 'Unable to updated product');
			}
		}

		return $this->render('::product:edit-form', array(
			'product' => $this->_product,
			'form'    => $form,
		));
	}

	public function processProductPricing($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);

		$form = $this->_getProductPricingForm();
		$form->handleRequest();
		if ($form->isValid() && $data = $form->getData()) {
			$product = $this->_product;

			$product->exportValue                = $data['export_value'];
			//set prices on the product
			foreach($data['prices']['currencies'] as $currency => $typePrices) {
				foreach ($typePrices as $type => $price) {
					$product->getPrices()[$type]->setPrice($currency, $price, $this->get('locale'));
				}
			}

			// save
			$product = $this->get('product.edit')->save($product);
			$product = $this->get('product.edit')->savePrices($product);

			if ($product->id) {
				$this->addFlash('success', 'Product updated successfully');
				return $this->redirectToReferer();
			} else {
				$this->addFlash('error', 'Unable to updated product');
			}
		}

		return $this->render('::product:edit-form', array(
			'product' => $this->_product,
			'form'    => $form,
		));
	}

	public function processStock($productID)
	{
		$this->_product 	= $this->get('product.loader')->getByID($productID);
		$locationCollection = $this->get('stock.locations');
		$this->_units   	= $this->_product->getAllUnits();
		$form           	= $this->_getStockForm($locationCollection->all());
		$stockManager 		= $this->get('stock.manager');

		if ($form->isValid() && $data = $form->getFilteredData()) {

			$note = $data['note'];
			$reason = $this->get('stock.movement.reasons')->get($data['reason']);

			if ($note) {
				$stockManager->setNote($note);
			}

			$stockManager->setReason($reason);
			$stockManager->setAutomated(false);

			foreach ($data['units'] as $unitID => $locationArray) {
				foreach ($locationArray as $location => $stock) {
					// remove all spaces and tabs and cast stock to int
					$stock = (int) (preg_replace('/\s+/','',$stock));

					if ($stock > 0) {
						$stockManager->increment(
							$this->_units[$unitID],
							$locationCollection->get($location),
							$stock
						);
					} else if ($stock < 0) {
						$stockManager->decrement(
							$this->_units[$unitID],
							$locationCollection->get($location),
							($stock * -1)
						);
					}
				}
			}

			if ($stockManager->commit()) {
				$this->addFlash('success', 'Successfully adjusted stock levels');
			}
		}

		return $this->redirectToRoute('ms.commerce.product.edit.stock', array('productID' => $this->_product->id));
	}


	public function processImage($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);
		$form = $this->_getImageForm();
		if ($form->isValid() && $data = $form->getFilteredData()) {
			$image          = new Image\Image;
			$image->product = $this->_product;
			$image->file    = $this->get('file_manager.file.loader')->getByID($data['image']);
			$image->type    = $data['type'];
			$image->locale  = $this->get('locale');

			foreach ($data['options'] as $option) {
				$image->options[$option['name']] = $option['value'];
			}

			$this->get('product.image.create')->create($image);
		}

		return $this->redirectToRoute('ms.commerce.product.edit.images', array('productID' => $this->_product->id));
	}

	/**
	 * Return the form for editing unit stock levels
	 *
	 * @return Handler Form Handler for stock editing
	 */
	protected function _getStockForm($locations)
	{
		// Make an overall form
		$mainForm = $this->get('form')
			->setName('units-stock')
			->setAction(
				$this->generateUrl('ms.commerce.product.edit.stock.action',
					array(
						'productID' => $this->_product->id
					))
			);

		$units = $this->get('form')
			->setName('units')
			->addOptions(array(
				'auto_initialize' => false,
		));

		// Create a nested form for each unit
		foreach ($this->_units as $id => $unit) {

			if (count($unit->options) <= 0) {
				continue;
			}

			$stockForm = $this->get('form')
				->setName($id)
				->addOptions(array(
					'auto_initialize' => false,
			));

			foreach ($locations as $location) {
				$stockForm
					->add(
						$location->name,
						'text',
						$this->trans('ms.commerce.product.stock.location.'.$location->name),
						array(
							'data' => '+0',
							'attr' => array('data-help-key' => 'ms.commerce.product.stock.level.help')
						)
					)
					->val()
					->number()
					->optional();
			}

			$units->add($stockForm, 'form');
		}

		$mainForm->add($units, 'form');

		$mainForm
			->add(
				'reason',
				'choice',
				$this->trans('ms.commerce.product.stock.reason.label'),
				array(
					'choices' 	=> $this->get('stock.movement.reasons')->all(),
					'required' 	=> true,
					'attr' 		=> array('data-help-key' => 'ms.commerce.product.stock.reason.help'),
				)
			);

		$mainForm
			->add('note', 'textarea', $this->trans('ms.commerce.product.stock.note.label'), array(
				'attr' => array('data-help-key' => 'ms.commerce.product.stock.note.help'),
			))
			->val()
			->optional();

		return $mainForm;
	}

	/**
	 * Function iterates over units and puts all possible actions
	 * into one array. The key is the name of the option and the
	 * value is the name with an uppercase first letter.
	 *
	 * @return array of all options available with pairs of [name] => Name
	 */
	protected function _getAllOptionsAndHeadings()
	{
		$headings = array();

		foreach ($this->_units as $unit) {
			foreach ($unit->options as $name => $value) {
				$headings[$name] = ucfirst($name);
			}
		}

		return $headings;
	}

	protected function _getImageForm()
	{
		$files   = (array) $this->get('file_manager.file.loader')->getAll();

		$choices = array();
		$allowedTypes = array(File\Type::IMAGE);
		foreach ($files as $file) {
			if ($allowedTypes) {
				if (!in_array($file->typeID, $allowedTypes)) {
					continue;
				}
			}

			$choices[$file->id] = $file->name;
		}

		uasort($choices, function($a, $b) {
			return strcmp($a, $b);
		});

		$form = $this->get('form')
			->setName('images')
			->setAction(
				$this->generateUrl('ms.commerce.product.edit.images',array('productID' => $this->_product->id))
			)
		;

		$imageTypes = $this->get('product.image.types')->all();

		$form->add('image', 'ms_file', $this->trans('ms.commerce.product.image.file.label'), array(
			'choices' => $choices,
			'empty_value' => 'Please select…',
			'attr' => array('data-help-key' => 'ms.commerce.product.image.file.help'),
		));

		$form->add('type', 'choice',  $this->trans('ms.commerce.product.image.type.label'), array(
			'choices' => $imageTypes,
			'attr' => array('data-help-key' => 'ms.commerce.product.image.type.help'),
		));

		$optionType = new Field\OptionType($this->get('option.loader')->getOptionNamesByProduct($this->_product),
			array('attr' => array(
				'data-help-key' => array(
					'name'  => 'ms.commerce.product.units.option.name.help',
					'value' => 'ms.commerce.product.units.option.value.help',
				)
			)));
		$optionType
			->setValueChoice($this->get('option.loader')->getOptionValuesByProduct($this->_product))
			->setNameLabel($this->trans('ms.commerce.product.image.option.name.label'))
			->setValueLabel($this->trans('ms.commerce.product.image.option.value.label'));

		$form->add('options', 'collection', 'Options',
			array(
				'type'         => $optionType,
				'label'        => 'Options',
				'allow_add'    => true,
				'allow_delete' => true
			)
		)->val()->optional();

		return $form;
	}

	/**
	 * Return the unit editing form
	 *
	 * @return Handler 		Form Handler for editing units and their prices
	 */
	protected function _getUnitForm()
	{
		return $this->createForm($this->get('product.form.unit.edit'), null, ['units' => $this->_units]);
	}

	/**
	 * Return the form for adding a new unit to a product
	 */
	protected function _addNewUnitForm()
	{
		$data = $this->get('http.session')->get(self::UNIT_DATA) ?: [];

		$form = $this->createForm($this->get('product.form.unit.add'), $data, [
			'action' => $this->generateUrl('ms.commerce.product.edit.units.create.action', [
				'productID' => $this->_product->id,
			]),
			'data' => $data,
		]);

		if ($data) {
			$this->get('http.session')->remove(self::UNIT_DATA);
		}

		return $form;
	}

	protected function _getProductAttributesForm()
	{
		return $this->get('product.form.attributes')->build($this->_product);
	}

	protected function _getProductDetailsForm()
	{
		return $this->get('field.form')->generate($this->_product->getDetails(), [
			'action' => $this->generateUrl('ms.commerce.product.edit.details.action', [
					'productID' => $this->_product->id,
				])
		]);
	}

	protected function _getProductPricingForm()
	{
		return $this->createForm($this->get('product.form.prices'), null, [
				'product'   => $this->_product,
				'currency'  => $this->get('currency.company'),
			]);
	}
}
