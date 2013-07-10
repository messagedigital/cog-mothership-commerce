<?php

namespace Message\Mothership\Commerce\Order;

class Address
{
	const DELIVERY = 'delivery';
	const BILLING  = 'billing';

	public $orderID;

	public $type;
	public $name;
	public $lines = array(
		1 => null,
		2 => null,
		3 => null,
		4 => null,
	);
	public $town;
	public $stateID;
	public $state;
	public $postcode;
	public $country;
	public $countryID;
	public $telephone;
}