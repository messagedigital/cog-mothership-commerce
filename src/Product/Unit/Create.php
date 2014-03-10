<?php

namespace Message\Mothership\Commerce\Product\Unit;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;
use Message\Cog\Localisation\Locale;

use Message\User\UserInterface;

/**
 * Product unit creator.
 *
 * @author Danny Hannah
 */
class Create implements DB\TransactionalInterface
{
	protected $_trans;
	protected $_transOverriden = false;
	protected $_user;
	protected $_locale;

	public function __construct(DB\Transaction $trans, UserInterface $user, Locale $locale)
	{
		$this->_trans  = $trans;
		$this->_user   = $user;
		$this->_locale = $locale;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setTransaction(DB\Transaction $transaction)
	{
		$this->_trans          = $transaction;
		$this->_transOverriden = true;
	}

	/**
	 * @see create
	 * @deprecated
	 *
	 * @param  Unit $unit The unit to create
	 *
	 * @return Unit       The saved unit
	 */
	public function save(Unit $unit)
	{
		return $this->create($unit);
	}

	/**
	 * Create a new unit
	 *
	 * @param  Unit $unit The unit to create
	 *
	 * @return Unit       The created unit
	 */
	public function create(Unit $unit)
	{
		if (is_null($unit->authorship->createdAt())) {
			$unit->authorship->create(new DateTimeImmutable, $this->_user->id);
		}

		$result = $this->_trans->add('
			INSERT INTO
				product_unit
			SET
				product_id   = :productID?i,
				visible      = :visibile?i,
				barcode      = :barcode?s,
				supplier_ref = :sup_ref?sn,
				weight_grams = :weight?i,
				created_at   = :createdAt?d,
				created_by   = :createdBy?i
		', [
			'productID' => $unit->product->id,
			'visible'	=> (bool) $unit->visible,
			'barcode'	=> $unit->barcode,
			'sup_ref'	=> $unit->supplierRef,
			'weight'	=> $unit->weight,
			'createdAt' => $unit->authorship->createdAt(),
			'createdBy' => $unit->authorship->createdBy()->id,
		]);

		$this->_trans->setIDVariable('UNIT_ID');
		$unit->id = '@UNIT_ID';

		$this->_trans->add('
			INSERT INTO
				product_unit_info
			SET
				unit_id     = :id?i,
				revision_id = 1,
				sku         = :sku?s
		', [
			'id'  => $unit->id,
			'sku' => $unit->sku,
		]);

		foreach ($unit->options as $name => $value) {
			$this->_trans->add('
				INSERT INTO
					product_unit_option
				SET
					unit_id      = :id?i,
					revision_id  = 1,
					option_name  = :name?s,
					option_value = :value?s
			', array(
				'id'    => $unit->id,
				'name'  => $name,
				'value' => $value
			));
		}

		$this->_savePrices($unit);

		if (!$this->_transOverriden) {
			$this->_trans->commit();
		}

		return $unit ? $unit : false;
	}

	protected function _savePrices(Unit $unit)
	{
		$options = array();
		$inserts = array();

		foreach ($unit->price as $type => $price) {

			if ($unit->price[$type]->getPrice('GBP', $this->_locale) === 0) {
				continue;
			}

			$options[] = $unit->id;
			$options[] = $type;
			$options[] = $unit->price[$type]->getPrice('GBP', $this->_locale);
			$options[] = 'GBP';
			$options[] = $this->_locale->getID();
			$inserts[] = '(?i,?s,?s,?s,?s)';
		}
		if ($options) {
			$result = $this->_trans->add(
				'INSERT INTO
					product_unit_price
					(
						unit_id,
						type,
						price,
						currency_id,
						locale
					)
				VALUES
					'.implode(',',$inserts).' ',
				$options
			);

			return $unit;
		}

		return false;
	}
}