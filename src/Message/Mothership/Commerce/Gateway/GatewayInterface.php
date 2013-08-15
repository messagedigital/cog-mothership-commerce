<?php

namespace Message\Mothership\Commerce\Gateway;

use Message\Mothership\Commerce\Order\Entity\Address\Address;
use Message\Mothership\Commerce\Order\Order;

interface GatewayInterface {

	public function setOrder(Order $order);

	public function setUsername($username);

	public function setRedirectURL($value);

	public function setPaymentAmount($amount, $currencyID);

	public function setDeliveryAddress(Address $address);

	public function setBillingAddress(Address $address);

	public function send();

}