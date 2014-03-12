<?php

namespace Message\Mothership\Commerce\Gateway;

use Omnipay\Omnipay;
use Omnipay\Common\CreditCard;

use Message\Mothership\Commerce\Order\Entity\Address\Address;
use Message\Mothership\Commerce\Order\Order;

class Wrapper implements GatewayInterface
{

	protected $_gateway;
	protected $_order;
	protected $_username;
	protected $_redirect;
	protected $_paymentAmount;
	protected $_transactionID;
	protected $_currencyID;
	protected $_card;
	protected $_user;

	protected $_response;
	protected $_request;

	public function getGateway()
	{
		return $this->_gateway;
	}

	public function getTransactionID()
	{
		return $this->_transactionID;
	}

	public function setGateway($gatewayName, $request)
	{
		$this->_gateway = Omnipay::create($gatewayName, null, $request);
		$this->_card = new CreditCard;

	}

	public function setOrder(Order $order)
	{
		$this->_order = $order;
	}

	public function setUsername($username)
	{
		$this->_gateway->setVendor($username);
	}

	public function setReference($reference)
	{
		$this->_gateway->setTransactionReference($reference);
	}

	public function setTransactionId($transactionID)
	{
		$this->_gateway->setTransactionId($transactionID);
	}

	public function setRedirectURL($redirectUrl)
	{
		$this->_redirect = $redirectUrl;
	}

	public function setPaymentAmount($amount, $currencyID)
	{
		$this->_paymentAmount = $amount;
		$this->_currencyID = $currencyID;
	}

	public function setDeliveryAddress(Address $address)
	{
		$this->_card->setShippingFirstName($address->forename);
		$this->_card->setShippingLastName($address->surname);

		$this->_card->setShippingAddress1($address->lines[1]);
		$this->_card->setShippingAddress2($address->lines[2]);

		$this->_card->setShippingCity($address->town);
		$this->_card->setShippingPostcode($address->postcode);
		$this->_card->setShippingState($address->stateID);
		$this->_card->setShippingCountry($address->countryID);

	}

	public function setBillingAddress(Address $address)
	{
		$this->_card->setEmail($this->_user->email);
		$this->_card->setFirstName($address->forename);
		$this->_card->setLastName($address->surname);

		$this->_card->setAddress1($address->lines[1]);
		$this->_card->setAddress2($address->lines[2]);

		$this->_card->setCity($address->town);
		$this->_card->setPostcode($address->postcode);
		$this->_card->setState($address->stateID);
		$this->_card->setCountry($address->countryID);
	}

	public function send()
	{

	}

	public function getResponse($id)
	{
		$result = $this->_query->run('SELECT dump FROM payment_dump WHERE transaction_id = ?s', array($id));

		foreach ($result as $row) {
			return unserialize($row->dump);
		}
	}
}