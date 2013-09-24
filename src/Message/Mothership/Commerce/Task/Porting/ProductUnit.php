<?php

namespace Message\Mothership\Commerce\Task\Porting;

class ProductUnit extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					catalogue_unit.catalogue_id,
					catalogue_unit.unit_id,
					catalogue_unit.visible,
					catalogue_unit_barcode.barcode,
					catalogue_unit.supplier_ref,
					NULL,
					catalogue_unit.unit_name
				FROM
					catalogue_unit
				JOIN
					catalogue_unit_barcode ON (catalogue_unit_barcode.unit_id =   catalogue_unit.unit_id)';

		$result = $old->run($sql);
		$new->add('TRUNCATE product_unit');
		$new->add('TRUNCATE product_unit_info');
		$output= '';
		foreach($result as $row) {
			$new->add('
				INSERT INTO
					product_unit
				(
					product_id,
					unit_id,
					visible,
					barcode,
					supplier_ref,
					weight_grams
				)
				VALUES
				(
					?,?,?,?,?,?
				)', (array) $row);
			$new->add('
				INSERT INTO
					product_unit_info
				(
					unit_id,
					revision_id,
					sku
				)
				VALUES
				(
					?,?,?
				)', array(
					$row->unit_id,
					1,
					$row->unit_name ?: $row->unit_id
			));
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported product units</info>');
		}

		return true;
    }
}