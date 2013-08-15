<?php

namespace Message\Mothership\Commerce\User\Address;

use Message\Mothership\Commerce\Address\Address as BaseAddress;
use Message\Cog\ValueObject\Authorship;

class Address extends BaseAddress
{
	public $userID;
	public $authorship;

	public function __construct()
	{
		$this->authorship = new Authorship;
	}
}
