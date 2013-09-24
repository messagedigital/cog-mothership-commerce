<?php

namespace Message\Mothership\Commerce\Task\Porting;

class ProductsPricing extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$new->add('TRUNCATE product_price');
		$new->add('TRUNCATE product_unit_price');

		$sql = 'SELECT
					catalogue_id AS product_id,
					price,
					rrp,
					cost_price,
					sale_price,
					wholesale_price,
					\'GBP\' AS currency_id,
					\'en_GB\' AS locale
				FROM
					catalogue_info';

		$result = $old->run($sql);

		$formatedData = array();
		foreach ($result as $data) {
			$formatedData[$data->product_id]['retail'] = array(
				'price' => $data->price,
				'currency_id' => $data->currency_id,
				'locale' => $data->locale,
			);
			$formatedData[$data->product_id]['rrp'] = array(
				'price' => $data->rrp,
				'currency_id' => $data->currency_id,
				'locale' => $data->locale,
			);
			$formatedData[$data->product_id]['cost'] = array(
				'price' => $data->cost_price,
				'currency_id' => $data->currency_id,
				'locale' => $data->locale,
			);
		}

		$output= '';
		foreach($formatedData as $productID => $data) {
			foreach ($data as $type => $values) {
				$new->add('
					INSERT INTO
						product_price
					(
						product_id,
						`type`,
						`price`,
						currency_id,
						locale
					)
				VALUES
					(
						:product_id?,
						:type?,
						:price?,
						:currency_id?,
						:locale?
					);', array(
						'product_id'  => $productID,
						'type'        => $type,
						'price'       => $values['price'],
						'currency_id' => $values['currency_id'],
						'locale'      => $values['locale'],
				));

			}
		}

		$sql = 'SELECT
					unit_id,
					price,
					rrp,
					cost_price,
					\'GBP\' AS currency_id,
					\'en_GB\' AS locale
				FROM
					catalogue_unit
				LEFT JOIN catalogue_unit_price USING (unit_id)
				LEFT JOIN catalogue_unit_rrp USING (unit_id)
				LEFT JOIN catalogue_unit_cost_price USING (unit_id)';

		$result = $old->run($sql);

		$formatedData = array();
		foreach ($result as $data) {
			$formatedData[$data->unit_id]['retail'] = array(
				'price' => $data->price,
				'currency_id' => $data->currency_id,
				'locale' => $data->locale,
			);
			$formatedData[$data->unit_id]['rrp'] = array(
				'price' => $data->rrp,
				'currency_id' => $data->currency_id,
				'locale' => $data->locale,
			);
			$formatedData[$data->unit_id]['cost'] = array(
				'price' => $data->cost_price,
				'currency_id' => $data->currency_id,
				'locale' => $data->locale,
			);
		}

		$output= '';
		foreach($formatedData as $unitID => $data) {
			foreach ($data as $type => $values) {
				if (is_null($values['price'])) {
					continue;
				}
				$new->add('
					INSERT INTO
						product_unit_price
					(
						unit_id,
						`type`,
						`price`,
						currency_id,
						locale
					)
				VALUES
					(
						:unit_id?,
						:type?,
						:price?,
						:currency_id?,
						:locale?
					);', array(
						'unit_id'  	  => $unitID,
						'type'        => $type,
						'price'       => $values['price'],
						'currency_id' => $values['currency_id'],
						'locale'      => $values['locale'],
				));

			}
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported product prices</info>');
		}

		return true;
    }
}