<?php

namespace Message\Mothership\Commerce\Controller\Product;

use Message\Cog\Controller\Controller;
use Message\Cog\ValueObject\DateTimeImmutable;
use Message\Mothership\Commerce\Product\Image;
use Message\Mothership\Commerce\Product\Stock;
use Message\Mothership\Commerce\Product\Stock\Movement\Reason\Reason;
use Message\Mothership\Commerce\Field;

class Edit extends Controller
{
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

		return $this->render('::product:edit-form', array(
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

		return $this->render('::product:edit-images', array(
			'locale'  => $this->get('locale'),
			'product' => $this->_product,
			'form'	  => $this->_getImageForm(),
		));
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

		if ($form->isValid() && $data = $form->getFilteredData()) {
			foreach ($data as $unitID => $values) {
				// original unit
				$unit = clone $this->_units[$unitID];
				// unit to update
				$changedUnit = clone $unit;

				$changedUnit->sku 		= $values['sku'];

				$changedUnit->weight 	= (null === $values['weight'] ? null : (int) $values['weight']);
				$changedUnit->visible 	= (int) (bool) $values['visible'];

				foreach ($values['price'] as $type => $value) {
					$changedUnit->price[$type]->setPrice('GBP', $value, $this->get('locale'));
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

		if ($form->isValid() && $data = $form->getFilteredData()) {
			$unit              = $this->get('product.unit');
			$unit->sku         = $data['sku'];
			$unit->weight 	   = $data['weight'];
			// TODO: Where does that 1 come from? -> constant??
			$unit->revisionID  = 1;
			$unit->product     = $this->_product;

			foreach ($data['price'] as $type => $value) {
				$unit->price[$type]->setPrice('GBP', $value, $this->get('locale'));
			}

			$unit->authorship->create(new DateTimeImmutable, $this->get('user.current'));

			foreach($data['options'] as $optionArray) {
				$unit->setOption($optionArray['name'], $optionArray['value']);
			}

			$unit = $this->get('product.unit.create')->create($unit);
		}

		return $this->redirectToRoute('ms.commerce.product.edit.units', array('productID' => $this->_product->id));

	}

	public function processProductAttributes($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);

		$form = $this->_getProductAttributesForm();
		if ($form->isValid() && $data = $form->getFilteredData()) {
			$product = $this->_product;

			$product->authorship->update(new DateTimeImmutable, $this->get('user.current'));

			$product->name                       = $data['name'];
			$product->shortDescription           = $data['short_description'];
			$product->displayName                = $data['display_name'];
			$product->year                       = $data['year'];
			$product->season                     = $data['season'];
			$product->description                = $data['description'];
			$product->category                   = $data['category'];
			$product->brand                   	 = $data['brand'];
			$product->exportDescription          = $data['export_description'];

			$product = $this->get('product.edit')->save($product);

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
		if ($form->isValid() && $data = $form->getFilteredData()) {
			$product = $this->_product;

			$product->authorship->update(new DateTimeImmutable, $this->get('user.current'));

			$product->supplierRef                = $data['supplier_ref'];
			$product->weight                	 = $data['weight_grams'];
			$product->fabric                     = $data['fabric'];
			$product->features                   = $data['features'];
			$product->careInstructions           = $data['care_instructions'];
			$product->sizing                     = $data['sizing'];
			$product->notes                      = $data['notes'];
			$product->tags                       = explode(',',$data['tags']);
			$product->exportManufactureCountryID = $data['export_manufacture_country_id'];

			$product = $this->get('product.edit')->save($product);
			$product = $this->get('product.edit')->saveTags($product);

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
		if ($form->isValid() && $data = $form->getFilteredData()) {
			$product = $this->_product;

			$product->authorship->update(new DateTimeImmutable, $this->get('user.current'));

			$product->taxRate                    = $data['tax_rate'];
			$product->taxStrategy                = $data['tax_strategy'];
			$product->exportValue                = $data['export_value'];

			foreach ($data as $key => $value) {
				if (preg_match("/^price/us", $key)) {
					$type = str_replace('price_', '', $key);
					$product->price[$type]->setPrice('GBP', $value, $this->get('locale'));
				}
			}

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
					$stock = (int)(preg_replace('/\s+/','',$stock));

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
		$allowedTypes = array();
		foreach ($files as $file) {
			if ($allowedTypes) {
				if (!in_array($file->typeID, $allowedTypes)) {
					continue;
				}
			}

			$choices[$file->id] = $file->name;
		}

		$form = $this->get('form')
			->setName('images')
			->setAction(
				$this->generateUrl('ms.commerce.product.edit.images',array('productID' => $this->_product->id))
			)
		;

		$imageTypes = $this->get('product.image.types')->all();

		$form->add('image', 'ms_file', $this->trans('ms.commerce.product.image.file.label'), array(
			'choices' => $choices,
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
		// Main form
		$mainForm = $this->get('form')
			->setName('units-edit')
			->setAction($this->generateUrl('ms.commerce.product.edit.units.action', array('productID' => $this->_product->id)));
		// Create a nested form for each of the units in this product
		foreach ($this->_units as $id => $unit) {
			$form = $this->get('form')
				->setName($id)
				->addOptions(array(
					'auto_initialize' => false,
				));

			// create a nested form for prices so we can have name="units-edit[unitID][price][retail]"
			$priceForm = $this->get('form')
				->setName('price')
				->addOptions(array(
					'auto_initialize' => false,
			));

			foreach ($unit->price as $type => $value) {
				$priceForm->add(
					$type,
					'money',
					$this->trans('ms.commerce.product.pricing.'.strtolower($type).'.label-sans'),
					array(
						'currency' => 'GBP',
						'data' 	   => $value->getPrice('GBP', $this->get('locale')),
						'attr'     => array(
							'data-help-key' => 'ms.commerce.product.pricing.'.strtolower($type).'.help',
						)
					)
				)
					->val()
					->number()
					->optional();
			}

			// Add the price form to the parent form
			$form->add($priceForm, 'form');


			// Work out the default options which should be 'selected' in the option drop downs
			$defaults = array();
			foreach ($unit->options as $type => $value) {
				$defaults[$type] = $value;
			}

			// create a nested form for the unit options so we can have name="units-edit[unitID][optionForm][colour]"
			$optionForm = $this->get('form')
				->setName('optionForm')
				->setDefaultValues($defaults)
				->addOptions(array(
					'auto_initialize' => false,
			));


			// Build the options
			foreach ($this->_getAllOptionsAndHeadings() as $type => $displayName) {
				// populate the select menu options by getting all available options from the DB
				$choices = array();
				foreach ($this->get('option.loader')->getByName($type) as $choice) {
					$choice = trim($choice);
					$choices[$choice] = $choice;
				}

				$optionForm->add($type, 'choice', ucfirst($type), array('choices' => $choices))
					->val()->optional();
			}
			// Add the option forms to the parent form
			$form->add($optionForm, 'form');

			// Populate the rest of the editbale unit attributes
			$form->add('sku', 'text','', array(
				'data' =>  $unit->sku,
				'attr' => array(
					'data-help-key' => 'ms.commerce.product.units.sku.help',
				)
			));
			$form->add('weight', 'text','', array(
				'data' => $unit->weight,
				'attr' => array(
					'data-help-key' => 'ms.commerce.product.details.weight-grams.help'
				)
			))
				->val()
				->optional()
				->number();

			$form->add('visible', 'checkbox','', array(
				'data' =>  $unit->visible,
				'attr' => array(
					'data-help-key' => 'ms.commerce.product.units.visible.help'
				)
			))
				->val()
				->optional();

			// Add the unit form to the main form
			$mainForm->add($form, 'form');
		}

		return $mainForm;
	}

	/**
	 * Return the form for adding a new unit to a product
	 */
	protected function _addNewUnitForm()
	{
		$headings = array();
		foreach ($this->get('option.loader')->getAllOptionNames() as $name => $value) {
			$headings[$value] = ucfirst($value);
		}

		$form = $this->get('form')
			->setName('new')
			->setAction($this->generateUrl('ms.commerce.product.edit.units.create.action', array('productID' => $this->_product->id)))
			->setDefaultValues(array(
				'visible' => false,
		));

		$form->add('sku', 'text','',array('attr' => array(
			'list' 			=> 'option_value',
			'placeholder' 	=> $this->trans('ms.commerce.product.units.sku.placeholder'),
			'data-help-key' => 'ms.commerce.product.image.option.units.sku.help'),
		));

		$form->add('weight', 'text','',  array('attr' => array(
			'data-help-key' => 'ms.commerce.product.details.weight-grams.help',
		)))
			->val()
			->number();

		$optionType = new Field\OptionType($headings,
			array('attr' => array(
				'data-help-key' => array(
					'name'  => 'ms.commerce.product.units.option.name.help',
					'value' => 'ms.commerce.product.units.option.value.help',
				)
			)));

		$optionType
			->setNameLabel($this->trans('ms.commerce.product.units.option.name.label'))
			->setValueLabel($this->trans('ms.commerce.product.units.option.value.label'));

		$form->add('options', 'collection', 'Options',
			array(
				'type'         => $optionType,
				'label'        => 'Options',
				'allow_add'    => true,
				'allow_delete' => true
			)
		);

		$priceForm = $this->get('form')
			->setName('price')
			->addOptions(array(
				'auto_initialize' => false,
			)
		);

		foreach ($this->get('product.price.types') as $type) {
			$priceForm
				->add($type, 'money', $this->trans('ms.commerce.product.pricing.'.strtolower($type).'.label-sans'),
					array(
						'currency' => 'GBP',
						'attr' => array('data-help-key' => 'ms.commerce.product.pricing.'.strtolower($type).'.help')
					)
				)
				->val()
				->number()
				->optional();
		}

		$form->add($priceForm, 'form');

		return $form;
	}

	protected function _getProductAttributesForm()
	{
		$form = $this->get('form')
			->setName('product-attributes-edit')
			->setAction($this->generateUrl('ms.commerce.product.edit.attributes.action', array('productID' => $this->_product->id)))
			->setMethod('post');

		$form->add('name', 'text', $this->trans('ms.commerce.product.attributes.name.label'), array(
			'data' => $this->_product->name,
			'attr' => array('data-help-key' => 'ms.commerce.product.attributes.name.help')
		))
			->val()
			->maxLength(255);

		$form->add('display_name', 'text', $this->trans('ms.commerce.product.attributes.display-name.label'), array(
			'data' => $this->_product->displayName,
			'attr' => array('data-help-key' => 'ms.commerce.product.attributes.display-name.help')
		))
			->val()
			->maxLength(255);

		$form->add('category', 'text', $this->trans('ms.commerce.product.attributes.category.label'), array(
			'data' => $this->_product->category,
			'attr' => array('data-help-key' => 'ms.commerce.product.attributes.category.help')
		))
			->val()
			->maxLength(255);

		$form->add('brand', 'text', $this->trans('ms.commerce.product.attributes.brand.label'), array(
			'data' => $this->_product->brand,
			'attr' => array('data-help-key' => 'ms.commerce.product.attributes.brand.help')
		))
			->val()
			->maxLength(255);


		$form->add('season', 'text', $this->trans('ms.commerce.product.attributes.season.label'), array(
			'data' => $this->_product->season,
			'attr' => array('data-help-key' => 'ms.commerce.product.attributes.season.help')
		))
			->val()
			->maxLength(255)
			->optional();


		$form->add('year', 'text', $this->trans('ms.commerce.product.attributes.year.label'), array(
			'data' => $this->_product->year,
			'attr' => array('data-help-key' => 'ms.commerce.product.attributes.year.help')
		))
			->val()
			->maxLength(4)
			->digit()
			->optional();

		$form->add('short_description', 'textarea', $this->trans('ms.commerce.product.attributes.short-description.label'), array(
			'data' => $this->_product->shortDescription,
			'attr' => array('data-help-key' => 'ms.commerce.product.attributes.short-description.help')
		));

		$form->add('description', 'textarea', $this->trans('ms.commerce.product.attributes.description.label'), array(
			'data' => $this->_product->description,
			'attr' => array('data-help-key' => 'ms.commerce.product.attributes.description.help')
		))
			->val()
			->optional();

		$form->add('export_description', 'textarea', $this->trans('ms.commerce.product.attributes.export-description.label'), array(
			'data' => $this->_product->exportDescription,
			'attr' => array('data-help-key' => 'ms.commerce.product.attributes.export-description.help')
		))
			->val()
			->optional();

		return $form;
	}

	protected function _getProductDetailsForm()
	{
		$form = $this->get('form')
			->setName('product-details-edit')
			->setAction($this->generateUrl('ms.commerce.product.edit.details.action', array('productID' => $this->_product->id)))
			->setMethod('post');

		$form->add('features', 'textarea', $this->trans('ms.commerce.product.details.features.label'), array(
			'data' => $this->_product->features,
			'attr' => array('data-help-key' => 'ms.commerce.product.details.features.help')
		))
			->val()
			->optional();


		$form
			->add('sizing', 'textarea', $this->trans('ms.commerce.product.details.sizing.label'), array(
				'data' => $this->_product->sizing,
				'attr' => array('data-help-key' => 'ms.commerce.product.details.sizing.help')
			))
			->val()
			->optional();

		$form
			->add('fabric', 'textarea', $this->trans('ms.commerce.product.details.fabric.label'), array(
				'data' => $this->_product->fabric,
				'attr' => array('data-help-key' => 'ms.commerce.product.details.fabric.help')
			))
			->val()
			->optional();

		$form
			->add('weight_grams', 'number', $this->trans('ms.commerce.product.details.weight-grams.label'), array(
				'data' => $this->_product->weight,
				'attr' => array(
					'data-help-key' => 'ms.commerce.product.details.weight-grams.help',
				)
			))
			->val()
			->number()
			->optional();

		$form
			->add('care_instructions', 'textarea', $this->trans('ms.commerce.product.details.care-instructions.label'), array(
				'data' => $this->_product->careInstructions,
				'attr' => array('data-help-key' => 'ms.commerce.product.details.care-instructions.help')
			))
			->val()
			->optional();


		$form
			->add('tags', 'textarea', $this->trans('ms.commerce.product.details.tags.label'), array(
				'data' => implode(',', $this->_product->tags),
				'attr' => array('data-help-key' => 'ms.commerce.product.details.tags.help')
			))
			->val()
			->optional();

		$form
			->add('supplier_ref', 'text', $this->trans('ms.commerce.product.details.supplier-ref.label'), array(
				'data' => $this->_product->supplierRef,
				'attr' => array('data-help-key' => 'ms.commerce.product.details.supplier-ref.help')
			))
			->val()
			->maxLength(255)
			->optional();

		$form
			->add(
				'export_manufacture_country_id',
				'choice',
				$this->trans('ms.commerce.product.details.export-manufacture-country.label'),
				array(
					'data' 	  => $this->_product->exportManufactureCountryID,
					'choices' => $this->get('country.list')->all(),
					'attr'    => array('data-help-key' => 'ms.commerce.product.details.export-manufacture-country.help'),
				)
			)
			->val()
			->optional();

		$form
			->add('notes', 'textarea', $this->trans('ms.commerce.product.details.notes.label'), array(
				'data' => $this->_product->notes,
				'attr' => array('data-help-key' => 'ms.commerce.product.details.notes.help')
			))
			->val()
			->optional();

		return $form;
	}

	protected function _getProductPricingForm()
	{
		$form = $this->get('form')
			->setName('product-pricing-edit')
			->setAction($this->generateUrl('ms.commerce.product.edit.pricing.action', array('productID' => $this->_product->id)))
			->setMethod('post');

		foreach ($this->_product->price as $type => $value) {
			$form->add(
				'price_'.$type,
				'money',
				$this->trans('ms.commerce.product.pricing.'.$type.'.label'),
				array(
					'currency' => 'GBP',
					'data' =>  $value->getPrice('GBP', $this->get('locale')),
					'attr' => array(
						'data-help-key' => 'ms.commerce.product.pricing.'.$type.'.help',
					)
				)
			)->val()->number();
		}

		$form->add('tax_rate', 'choice', $this->trans('ms.commerce.product.pricing.tax-rate.label'), array(
			'data' => $this->_product->taxRate,
			'attr' => array('data-help-key' => 'ms.commerce.product.pricing.tax-rate.help'),
			'choices' => $this->get('product.tax.rates'),
			'empty_value' => '-- Please select --'
		))
			->val()
			->number()
			->maxLength(255);

		$form->add('tax_strategy', 'choice', $this->trans('ms.commerce.product.pricing.tax-strategy.label'), array(
			'choices' => array(
				'inclusive' => $this->trans('ms.commerce.product.pricing.tax-strategy.choices.inclusive'),
				'exclusive' => $this->trans('ms.commerce.product.pricing.tax-strategy.choices.exclusive'),
			),
			'data' => $this->_product->taxStrategy,
			'required' => true, // will remove the empty value from the choice-list
			'attr' 	   => array('data-help-key' => 'ms.commerce.product.pricing.tax-strategy.help'),
		));

		$form->add('export_value', 'money', $this->trans('ms.commerce.product.pricing.export-value.label'), array(
			'currency' => 'GBP',
			'data' => $this->_product->exportValue,
			'attr' => array('data-help-key' => 'ms.commerce.product.pricing.export-value.help')
		))
			->val()
			->number()
			->optional();

		return $form;
	}
}
