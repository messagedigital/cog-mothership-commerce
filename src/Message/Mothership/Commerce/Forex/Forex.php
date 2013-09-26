<?php

namespace Message\Mothership\Commerce\Forex;

use LogicException;
use InvalidArgumentException;

/**
 * Provides foreign exchange currency conversions.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Forex {

	protected $_query;
	protected $_currencies;
	protected $_baseCurrency;

	protected $_rates;

	/**
	 * Constructor.
	 *
	 * @param Message\Cog\DB\Query $query
	 * @param string               $baseCurrency Base currency to do conversions against.
	 * @param array                $currencies   List of currencies that can be used in conversions.
	 */
	public function __construct($query, $baseCurrency, array $currencies)
	{
		$this->_query           = $query;
		$this->_currencies      = $currencies;
		$this->_baseCurrency    = $baseCurrency;
	}

	/**
	 * Create a converter, and if possible return the converted value.
	 *
	 * @param  float|null       $amount Amount to convert
	 * @param  string|null      $to     Currency to convert to
	 * @param  string|null      $from   Currency to convert from
	 *
	 * @return float
	 */
	public function convert($amount = null, $to = null, $from = null)
	{
		if ($amount !== null) {
			$this->amount($amount);
		}

		if ($to !== null) {
			$this->to($to);
		}

		if ($from !== null) {
			$this->from($from);
		}
		else {
			$this->from($this->_baseCurrency);
		}

		if (null === $this->_amount) {
			throw new LogicException("The amount to convert must be set");
		}
		if (null === $this->_to) {
			throw new LogicException("The 'to currency' must be set");
		}

		if (null === $this->_from) {
			throw new LogicException("The 'from currency' must be set");
		}

		return $this->_amount * $this->_to->rate / $this->_from->rate;
	}

	/**
	 * Set the amount to convert.
	 *
	 * @param  int $amount Amount to convert
	 *
	 * @return Converter
	 */
	public function amount($amount)
	{
		$this->_amount = (float) $amount;

		return $this;
	}

	/**
	 * Set the currency to convert from.
	 *
	 * @param  string $currency Currency to convert from
	 *
	 * @return Converter
	 */
	public function from($currency)
	{
		$from = $this->getRateByCurrency($currency);

		$this->_from = $from;

		return $this;
	}

	/**
	 * Set the currency to convert to.
	 *
	 * @param  string $currency Currency to convert to
	 *
	 * @return Converter
	 */
	public function to($currency)
	{
		$to = $this->getRateByCurrency($currency);

		$this->_to = $to;

		return $this;
	}

	/**
	 * Get the currency rates.
	 *
	 * @return array
	 */
	public function getRates()
	{
		if ($this->_rates == null) {
			$this->_rates = array();

			$results = $this->_query->run('
				SELECT
					currency,
					rate
				FROM
					forex_rate
			');

			foreach ($results as $row) {
				$this->_rates[strtoupper($row->currency)] = $row;
			}
		}

		return $this->_rates;
	}

	/**
	 * Get a rate by currency.
	 *
	 * @param  string $currency
	 *
	 * @return stdObject
	 */
	public function getRateByCurrency($currency)
	{
		$rates = $this->getRates();

		$currency = strtoupper($currency);

		if (! isset($rates[$currency])) {
			throw new InvalidArgumentException(sprintf("The currency '%s' is not valid", $currency));
		}

		return $rates[$currency];
	}

}