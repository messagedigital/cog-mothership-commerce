<?php

namespace Message\Mothership\Commerce\Order\Status;

use Message\Cog\ValueObject\Authorship;

/**
 * Order status model.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Status
{
	public $code;
	public $name;

	/**
	 * Constructor
	 *
	 * @param int    $code Status code
	 * @param string $name Status name
	 */
	public function __construct($code, $name)
	{
		$this->code = (int) $code;
		$this->name = $name;

		$this->authorship = new Authorship;
	}

	/**
	 * Get this status as a string.
	 *
	 * @return string The status code & name as "(code) name"
	 */
	public function __toString()
	{
		return sprintf('(%d) %s', $this->code, $this->name);
	}
}