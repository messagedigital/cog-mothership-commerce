<?php

namespace Message\Mothership\Commerce\Task\Porting;

class Products extends Porting
{

    public function process()
    {
        $uwOld = $this->getFromConnection();
		$uwNew = $this->getToCOnnection();

		$new = new \Message\Cog\DB\Transaction($uwNew);
		$old = new \Message\Cog\DB\Query($uwOld);

		$sql = 'SELECT
					catalogue.catalogue_id,
					catalogue.product_id,
					catalogue.product_year AS year,
					UNIX_TIMESTAMP() AS created_at,
					NULL AS created_by,
					NULL AS udpated_at,
					NULL AS updated_by,
					IF (catalogue.date_deleted IS NOT NULL, UNIX_TIMESTAMP(catalogue.date_deleted), NULL) AS deleted_date,
					NULL AS deleted_by,
					brand_name AS brand,
					catalogue_product.product_name AS name,
					order_tax.tax_rate,
					catalogue_product.supplier_ref,
					catalogue_product.weight,
					category_name AS category
				FROM
					catalogue
				JOIN
					catalogue_product USING (product_id)
				JOIN
					brand_info USING (brand_id)
				JOIN
					category_info USING (category_id)
				JOIN
					order_tax USING (tax_code)';

		$result = $old->run($sql);
		$new->add('TRUNCATE product');
		$new->add('TRUNCATE product_info');
		$output= '';
		foreach($result as $row) {
			$new->add('
				INSERT INTO
					product
				(
					product_id,
					year,
					created_at,
					created_by,
					updated_at,
					updated_by,
					deleted_at,
					deleted_by,
					brand,
					name,
					tax_rate,
					supplier_ref,
					weight_grams,
					category
				)
				VALUES
				(
					:product_id?,
					:year?,
					:created_at?,
					:created_by?,
					:updated_at?,
					:updated_by?,
					:deleted_at?,
					:deleted_by?,
					:brand?,
					:name?,
					:tax_rate?,
					:supplier_ref?,
					:weight_grams?,
					:category?
				)', (array) $row);
		}

		$sql = 'SELECT
					catalogue_id      AS product_id,
					\'en_GB\'         AS locale,
					display_name      AS display_name,
					season            AS season,
					description       AS description,
					fabric            AS fabric,
					care_instructions AS care_instructions,
					short_description AS short_description,
					sizing            AS sizing,
					notes             AS notes
				FROM
					catalogue_info';

		$result = $old->run($sql);

		foreach($result as $row) {
			$new->add('INSERT INTO
				product_info
				(
					product_id,
					locale,
					display_name,
					season,
					description,
					fabric,
					care_instructions,
					short_description,
					sizing,
					notes
				)
				VALUES
				(
					:product_id?,
					:locale?,
					:display_name?,
					:season?,
					:description?,
					:fabric?,
					:care_instructions?,
					:short_description?,
					:sizing?,
					:notes?
				)', (array) $row);
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported products</info>');
		}

		return true;
    }
}