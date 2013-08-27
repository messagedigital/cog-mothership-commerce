<?php

namespace Message\Mothership\Commerce\Product;

use Message\Mothership\Commerce\Product\Product;
use Message\Cog\ValueObject\DateTimeImmutable;
use Message\Cog\Localisation\Locale;

use Message\User\UserInterface;

use Message\Cog\DB\Query;
use Message\Cog\DB\Result;

/**
 * Class for updating the attributes of a given Product object to the DB
 */
class Edit
{
	protected $_query;
	protected $_user;
	protected $_locale;

	public function __construct(Query $query, Locale $locale, UserInterface $user)
	{
		$this->_query  = $query;
		$this->_user   = $user;
		$this->_locale = $locale;
	}

	/**
	 * Handles the bulk updating of most of the product properties
	 *
	 * @param  Product $product Updated Product object to save
	 *
	 * @return Product          Saved Product object
	 */
	public function save(Product $product)
	{
		$result = $this->_query->run(
			'UPDATE
				product
			 JOIN
			 	product_info ON (product.product_id = product_info.product_id AND product_info.locale = :localeID?s)
			 LEFT JOIN
			 	product_export ON (product.product_id = product_export.product_id AND product_export.locale = :localeID?s)
			 SET
				product.year         = :year?i,
				product.updated_at   = :updated_at?i,
				product.updated_by   = :updated_by?i,
				product.brand     	 = :brand?s,
				product.name         = :name?s,
				product.tax_rate     = :tax_rate?s,
				product.tax_strategy = :tax_strategy?s,
				product.supplier_ref = :supplier_ref?s,
				product.weight_grams = :weight_grams?i,
				product.category     = :category?s,

				product_info.display_name      = :display_name?s,
				product_info.season            = :season?s,
				product_info.description       = :description?s,
				product_info.fabric            = :fabric?s,
				product_info.features          = :features?s,
				product_info.care_instructions = :care_instructions?s,
				product_info.short_description = :short_description?s,
				product_info.sizing            = :sizing?s,
				product_info.notes             = :notes?s,

				product_export.export_value       = :exportValue?,
				product_export.export_description = :exportDescription?,
				product_export.export_manufacture_country_id  = :exportCountryID?s
			WHERE
				product.product_id = :productID?i
			', array(
				'year'              => $product->year,
				'updated_at'        => $product->authorship->updatedAt(),
				'udpated_by'        => $product->authorship->updatedBy(),
				'brand'          	=> $product->brand,
				'name'              => $product->name,
				'tax_rate'          => $product->taxRate,
				'tax_strategy'      => $product->taxStrategy,
				'supplier_ref'      => $product->supplierRef,
				'weight_grams'      => $product->weight,
				'display_name'      => $product->displayName,
				'season'            => $product->season,
				'description'       => $product->description,
				'fabric'            => $product->fabric,
				'features'          => $product->features,
				'care_instructions' => $product->careInstructions,
				'short_description' => $product->shortDescription,
				'sizing'            => $product->sizing,
				'notes'             => $product->notes,
				'category'          => $product->category,
				'productID'			=> $product->id,
				'localeID'			=> $this->_locale->getID(),
				'exportValue'		=> $product->exportValue,
				'exportDescription'	=> $product->exportDescription,
				'exportCountryID'	=> $product->exportManufactureCountryID,
			)
		);

		return $product;
	}

	/**
	 * Updates any additions or deletions of tags for the given product
	 *
	 * @param  Product $product Product object to update
	 *
	 * @return Product          Saved Product object
	 */
	public function saveTags(Product $product)
	{
		$options = array();
		$inserts = array();
		foreach ($product->tags as $tag) {
			$options[] = $product->id;
			$options[] = trim($tag);
			$inserts[] = '(?i,?s)';
		}

		// Delete any tags associated with this product
		$this->_query->run(
			'DELETE FROM
				product_tag
			WHERE
				product_id = ?i',
			array(
				$product->id
			)
		);

		// Insert all the tags
		$result = $this->_query->run(
			'INSERT INTO
				product_tag
				(
					product_id,
					name
				)
			VALUES
				'.implode(',',$inserts).' ',
			$options
		);

		return $product;
	}

	/**
	 * Update the prices for the product
	 *
	 * @param  Product $product Product object to update
	 *
	 * @return Product          Saved Product object
	 */
	public function savePrices(Product $product)
	{
		$options = array();
		$inserts = array();

		foreach ($product->price as $type => $price) {
			$options[] = $product->id;
			$options[] = $type;
			$options[] = $product->price[$type]->getPrice('GBP', $this->_locale);
			$options[] = 'GBP';
			$options[] = $this->_locale->getID();
			$inserts[] = '(?i,?s,?s,?s,?s)';
		}

		$result = $this->_query->run(
			'REPLACE INTO
				product_price
				(
					product_id,
					type,
					price,
					currency_id,
					locale
				)
			VALUES
				'.implode(',',$inserts).' ',
			$options
		);

		return $product;
	}

	/**
	 * Save the new image to the product object
	 *
	 * @param  Product 	$product 	Product object to update
	 * @param  Image 	$image 		Image object to save
	 *
	 * @return Product          	Saved Product object
	 */
	public function saveImage(Product $product, Image $image)
	{
		$result = $this->_query->run(
			'REPLACE INTO
				product_image
			SET
				product_id = ?i,
				file_id = ?i,
				locale = ?,
				type = ?s,
				option_name = ?sn,
				option_value = ?sn',
			array(
				$product->id,
				$image->fileID,
				$image->locale->getID(),
				$image->type,
				$image->optionName,
				$image->optionValue
			)
		);

		return $product;
	}

}
