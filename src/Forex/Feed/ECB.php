<?php

namespace Message\Mothership\Commerce\Forex\Feed;

use Message\Cog\DB\Transaction;
use DateTime;

class ECB implements FeedInterface {

	protected $_query;

	protected $_url = 'http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

	public function __construct(Transaction $query)
	{
		$this->_query = $query;
	}

	public function fetch()
	{
		$rates = array(
			'EUR' => 1.00
		);

		if ($xml = @simplexml_load_file($this->_url)) {
			foreach ($xml->Cube->Cube->Cube as $item) {
				$currency = $rate = null;

				foreach ($item->attributes() as $key => $val) {
					$$key = (string) $val;
				}

				$rates[$currency] = (float) $rate;
			}

			unset($xml, $item, $key, $val);
		}

		// Insert each rate into the table
		foreach ($rates as $currency => $rate) {
			$this->_query->add('
				INSERT INTO
					forex_rate (
						currency,
						rate,
						created_at
					)
				VALUES (
					:currency?s,
					:rate?f,
					:createdAt?d
				)
				ON DUPLICATE KEY UPDATE
					rate = :rate?f,
					updated_at = :updatedAt?d
			', array(
				'currency'  => strtoupper($currency),
				'rate'      => $rate,
				'createdAt' => new DateTime,
				'updatedAt' => new DateTime
			));
		}

		$this->_query->commit();

		return $rates;
	}

}