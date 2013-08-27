<?php

namespace Message\Mothership\Commerce\Controller\Product;

use Message\Cog\Controller\Controller;
use Message\Cog\ValueObject\DateTimeImmutable;
use Message\Mothership\Commerce\Product\Image;
use Message\Mothership\Commerce\Product\Stock;
use Message\Mothership\Commerce\Product\Stock\Movement\Reason\Reason;

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
	public function index($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);

		return $this->render('::product:edit', array(
			'product' => $this->_product,
			'form'    => $this->_getProductForm(),
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
		$this->_units 	= $this->_product->getUnits();

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
		$movementIterator->addUnits($this->_product->getUnits());

		return $this->render('::product:edit-stock', array(
			'headings'  	   => $this->_getAllOptionsAndHeadings(),
			'locale'  		   => $this->get('locale'),
			'product' 		   => $this->_product,
			'units'   		   => $this->_units,
			'locations' 	   => $locations,
			'form'	  		   => $this->_getUnitStockForm($locations),
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

	public function processStock($productID)
	{
		$this->_product 	= $this->get('product.loader')->getByID($productID);
		$locationCollection = $this->get('stock.locations');
		$this->_units   	= $this->_product->getUnits();
		$form           	= $this->_getUnitStockForm($locationCollection->all());
		$stockManager 		= $this->get('stock.manager');

		if ($form->isValid() && $data = $form->getFilteredData()) {
			$note = $data['note'];
			$reason = $this->get('stock.movement.reasons')->get($data['reason']);

			if($note) {
				$stockManager->setNote($note);
			}

			$stockManager->setReason($reason);
			$stockManager->setAutomated(false);

			foreach ($data['units'] as $unitID => $locationArray) {
				foreach($locationArray as $location => $stock) {
					// remove all spaces and tabs and cast stock to int
					$stock = (int)(preg_replace('/\s+/','',$stock));

					if($stock > 0) {
						$stockManager->increment(
							$this->_units[$unitID],
							$locationCollection->get($location),
							$stock
						);
					} else if($stock < 0) {
						$stockManager->decrement(
							$this->_units[$unitID],
							$locationCollection->get($location),
							($stock * -1)
						);
					}
				}
			}

			if($stockManager->commit()) {
				$this->addFlash('success', 'Successfully adjusted stock levels');
			}
		}

		return $this->redirectToRoute('ms.commerce.product.edit.stock', array('productID' => $this->_product->id));
	}

	/**
	 * Return the form for editing unit stock levels
	 *
	 * @return Handler Form Handler for stock editing
	 */
	protected function _getUnitStockForm($locations)
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
						// trans this!
						$location->displayName,
						array('attr' =>
							array('value' =>  '+0')
						)
					)
					->val()
					->optional();
			}

			$units->add($stockForm->getForm(), 'form');
		}

		$mainForm->add($units->getForm(), 'form');

		$mainForm
			->add(
				'reason',
				'choice',
				'Reason',
				array(
					'choices' 	=> $this->get('stock.movement.reasons')->all(),
					'required' 	=> true,
				)
			);

		$mainForm
			->add('note', 'textarea', 'Note')
			->val()
			->optional();

		return $mainForm;
	}
	public function imagesProcess($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);
		$form = $this->_getImageForm();
		if ($form->isValid() && $data = $form->getFilteredData()) {
			$image = new Image(
				$data['image'],
				$this->get('product.image.types')->get($data['type']),
				$this->get('locale'),
				null,
				$data['option_name'],
				$data['option_value']
			);

			$this->get('product.edit')->saveImage($this->_product, $image);
		}

		return $this->redirectToRoute('ms.commerce.product.edit.images', array('productID' => $this->_product->id));
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

		foreach($this->_units as $unit) {
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
		);

		$imageTypes = array();
		foreach ($this->get('product.image.types')->all() as $image) {
			$imageTypes[$image->type] = ucfirst($image->type);
		}

		$form->add('image', 'ms_file', 'image', array('choices' => $choices));
		$form->add('type', 'choice', 'image type', array(
			'choices' => $imageTypes
		));

		$optionNames = array();

		$form->add('option_name', 'choice', 'Option name', array(
			'choices' => $this->get('option.loader')->getOptionNamesByProduct($this->_product),
		))->val()->optional();

		$form->add('option_value', 'choice', 'Option Value', array(
			'choices' => $this->get('option.loader')->getOptionValuesByProduct($this->_product),

		))->val()->optional();

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
				->setDefaultValues(array(
					'visible' => (bool) $unit->visible,
				))
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
				$priceForm->add($type, 'text',$this->trans('ms.commerce.product.label.price-sans.'.strtolower($type)), array('attr' => array('value' =>  $value->getPrice('GBP', $this->get('locale')))))
					->val()->optional();
			}

			// Add the price form to the parent form
			$form->add($priceForm->getForm(), 'form');

			// Work out the default options which should be 'selected' in the option drop downs
			$defaults = array();
			foreach ($unit->options as $type => $value) {
				$defaults[$type] = $value;
			}

			// create a nested form for the unit options so we can have name="units-edit[unitID][options][colour]"
			$optionForm = $this->get('form')
				->setName('options')
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

				$optionForm->add($type, 'choice','', array('choices' => $choices))
					->val()->optional();
			}
			// Add the option forms to the parent form
			$form->add($optionForm->getForm(), 'form');

			// Populate the rest of the editbale unit attributes
			$form->add('sku', 'text','', array('attr' => array('value' =>  $unit->sku)));
			$form->add('weight', 'text','', array('attr' => array('value' =>  $unit->weight)))
				->val()->optional();
			$form->add('visible', 'checkbox','',array('attr' => array('value' =>  $unit->visible)))
				->val()->optional();
			$form->add('delete', 'checkbox','')
				->val()->optional();

			// Add the unit form to the main form
			$mainForm->add($form->getForm(), 'form');
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

		$form->add('sku', 'text','',array('attr' => array('list' => 'option_value', 'placeholder' => 'SKU')));
		$form->add('weight', 'text','');

		$form->add('option_name_1', 'choice','Option name 1',
			array(
				'choices' => $headings,
				'empty_value' => 'Select option',
			)
		);
		$form->add('option_value_1', 'text','Option value', array('attr' => array('list' => 'option_value', 'placeholder' => 'Value')));

		$form->add('option_name_2', 'choice','Option name 2',
			array(
				'choices' => $headings,
				'empty_value' => 'Select option',
			)
		);
		$form->add('option_value_2', 'text','Option value', array('attr' => array('list' => 'option_value', 'placeholder' => 'Value')));

		$priceForm = $this->get('form')
			->setName('price')
			->addOptions(array(
				'auto_initialize' => false,
		));

		foreach ($this->get('product.price.types') as $type) {
			$priceForm->add($type, 'text', $this->trans('ms.commerce.product.label.price-sans.'.strtolower($type)))
				->val()->optional();
		}

		$form->add($priceForm->getForm(), 'form');

		return $form;
	}

	/**
	 * Process the updating of the units data
	 *
	 * @param  int 		$productID ProductID to load
	 */
	public function unitProcess($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);
		$this->_units   = $this->_product->getUnits();
		$form           = $this->_getUnitForm();

		if ($form->isValid() && $data = $form->getFilteredData()) {
			foreach ($data as $unitID => $values) {
				// original unit
				$unit = clone $this->_units[$unitID];
				// unit to update
				$changedUnit = clone $unit;

				if ($values['delete']) {
					$this->get('product.unit.delete')->delete($unit);

					continue;
				}
				$changedUnit->sku 		= $values['sku'];
				$changedUnit->weight 	= (int) $values['weight'];
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

	public function addUnitProccess($productID)
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
			$unit->setOption($data['option_name_1'], $data['option_value_1']);

			if (!empty($data['option_name_2']) && !empty($data['option_value_2'])) {
				$unit->setOption($data['option_name_2'], $data['option_value_2']);
			}

			$unit = $this->get('product.unit.create')->save($unit);
			$unit = $this->get('product.unit.create')->savePrices($unit);
		}

		return $this->redirectToRoute('ms.commerce.product.edit.units', array('productID' => $this->_product->id));

	}

	public function process($productID)
	{
		$this->_product = $this->get('product.loader')->getByID($productID);

		$form = $this->_getProductForm();
		if ($form->isValid() && $data = $form->getFilteredData()) {
			$product = $this->_product;

			$product->authorship->update(new DateTimeImmutable, $this->get('user.current'));

			$product->name                       = $data['name'];
			$product->shortDescription           = $data['short_description'];
			$product->displayName                = $data['display_name'];
			$product->year                       = $data['year'];
			$product->taxRate                    = $data['tax_rate'];
			$product->taxStrategy                = $data['tax_strategy'];
			$product->supplierRef                = $data['supplier_ref'];
			$product->weight                	 = $data['weight_grams'];
			$product->season                     = $data['season'];
			$product->description                = $data['description'];
			$product->fabric                     = $data['fabric'];
			$product->category                   = $data['category'];
			$product->brand                   	 = $data['brand'];
			$product->features                   = $data['features'];
			$product->careInstructions           = $data['care_instructions'];
			$product->sizing                     = $data['sizing'];
			$product->notes                      = $data['notes'];
			$product->tags                       = explode(',',$data['tags']);
			$product->exportDescription          = $data['export_description'];
			$product->exportValue                = $data['export_value'];
			$product->exportManufactureCountryID = $data['export_manufacture_country_id'];

			foreach ($data as $key => $value) {
				if(preg_match("/^price/us", $key)) {
					$type = str_replace('price_', '', $key);
					$product->price[$type]->setPrice('GBP', $value, $this->get('locale'));
				}
			}

			$product = $this->get('product.edit')->save($product);
			$product = $this->get('product.edit')->saveTags($product);
			$product = $this->get('product.edit')->savePrices($product);

			if ($product->id) {
				$this->addFlash('success', 'Product updated successfully');
			}
		}

		return $this->redirectToRoute('ms.commerce.product.edit', array('productID' => $this->_product->id));

	}

	protected function _getProductForm()
	{
		$form = $this->get('form')
			->setName('product-edit')
			->setAction($this->generateUrl('ms.commerce.product.edit.action', array('productID' => $this->_product->id)))
			->setDefaultValues(array(
				'name'                          => $this->_product->name,
				'short_description'             => $this->_product->shortDescription,
				'display_name'                  => $this->_product->displayName,
				'year'                          => $this->_product->year,
				'tax_rate'                      => $this->_product->taxRate,
				'tax_strategy'                  => $this->_product->taxStrategy,
				'supplier_ref'                  => $this->_product->supplierRef,
				'weight_grams'                  => $this->_product->weight,
				'season'                        => $this->_product->season,
				'description'                   => $this->_product->description,
				'category'                   	=> $this->_product->category,
				'brand'                   		=> $this->_product->brand,
				'fabric'                        => $this->_product->fabric,
				'features'                      => $this->_product->features,
				'care_instructions'             => $this->_product->careInstructions,
				'sizing'                        => $this->_product->sizing,
				'notes'                         => $this->_product->notes,
				'tags'                          => implode(',',$this->_product->tags),
				'export_description'            => $this->_product->exportDescription,
				'export_value'                  => $this->_product->exportValue,
				'export_manufacture_country_id' => $this->_product->exportManufactureCountryID,
			))
			->setMethod('post');

		$form->add('name', 'text', $this->trans('ms.commerce.product.label.name'))
			->val()->maxLength(255);

		$form->add('display_name', 'text', $this->trans('ms.commerce.product.label.display-name'))
			->val()->maxLength(255);

		$form->add('category', 'text', $this->trans('ms.commerce.product.label.category'))
			->val()->maxLength(255);
		$form->add('brand', 'text', $this->trans('ms.commerce.product.label.brand'))
			->val()->maxLength(255);

		$form->add('short_description', 'textarea', $this->trans('ms.commerce.product.label.short-description'));
		$form->add('description', 'textarea', $this->trans('ms.commerce.product.label.description'))
			->val()->optional();
		$form->add('fabric', 'textarea', $this->trans('ms.commerce.product.label.fabric'))
			->val()->optional();
		$form->add('features', 'textarea', $this->trans('ms.commerce.product.label.features'))
			->val()->optional();
		$form->add('care_instructions', 'textarea', $this->trans('ms.commerce.product.label.care-instructions'))
			->val()->optional();
		$form->add('sizing', 'textarea', $this->trans('ms.commerce.product.label.sizing'))
			->val()->optional();
		$form->add('notes', 'textarea', $this->trans('ms.commerce.product.label.notes'))
			->val()->optional();
		$form->add('tags', 'textarea', $this->trans('ms.commerce.product.label.tags'))
			->val()->optional();

		$form->add('year', 'text', $this->trans('ms.commerce.product.label.year'))
			->val()
				->maxLength(4)
				->optional();

		$form->add('tax_rate', 'text', $this->trans('ms.commerce.product.label.tax-rate'))
			->val()->maxLength(255);

		$form->add('tax_strategy', 'choice', $this->trans('ms.commerce.product.tax-strategy'), array(
			'choices'     => array(
				'inclusive' => 'Inclusive',
				'exclusive' => 'Exclusive',
			),
			'empty_value' => false,
		));

		$form->add('supplier_ref', 'text', $this->trans('ms.commerce.product.label.supplier-ref'))
			->val()
				->maxLength(255)
				->optional();

		foreach ($this->_product->price as $type => $value) {
			$form->add('price_'.$type, 'text', $this->trans('ms.commerce.product.label.price.'.$type),array('attr' => array('value' =>  $value->getPrice('GBP', $this->get('locale')))));
		}

		$form->add('weight_grams', 'number', $this->trans('ms.commerce.product.label.weight-grams'))
			->val()->maxLength(255);
		$form->add('season', 'text', $this->trans('ms.commerce.product.label.season'))
			->val()
				->maxLength(255)
				->optional();

		$form->add('export_description', 'textarea', $this->trans('ms.commerce.product.label.export-description'))
			->val()->optional();
		$form->add('export_value', 'text', $this->trans('ms.commerce.product.label.export-value'))
			->val()->optional();
		$form->add('export_manufacture_country_id', 'choice', $this->trans('ms.commerce.product.label.export-manufacture-country'), array(
			'choices' => $this->get('country.list')->all(),
		))->val()->optional();

		return $form;
	}
}
