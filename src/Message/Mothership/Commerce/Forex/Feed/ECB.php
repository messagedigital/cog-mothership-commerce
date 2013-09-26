<?php

namespace Message\Mothership\Commerce\Forex\Feed;

class ECB implements FeedInterface {

	protected $_query;

	protected $_url = 'http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

	public function __construct($query)
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

		// Truncate the rates table
		$this->_query->run('
			TRUNCATE TABLE
				forex_rate
		');

		// Insert each rate into the table
		foreach ($rates as $currency => $rate) {
            $this->_query->run('
            	INSERT INTO
            		forex_rate
            	SET
            		currency = ?s,
            		rate     = ?f
            ', array(
            	strtoupper($currency),
            	$rate
            ));
        }

        return $rates;
	}

}