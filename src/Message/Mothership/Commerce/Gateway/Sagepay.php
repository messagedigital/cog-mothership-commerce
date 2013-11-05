<?php

namespace Message\Mothership\Commerce\Gateway;

use Message\User\UserInterface;
use Message\Cog\HTTP\Request;
use Message\Cog\Cache\Instance;
use Message\Mothership\Commerce\Order\Order;
use Message\Mothership\Commerce\Order\Entity\Payment\Payment;
use Message\Mothership\Commerce\Order\Entity\Address\Address;

class Sagepay extends Wrapper
{
	protected $_data;
	protected $_request;
	protected $_config;
	protected $_cache;

	public function __construct(
		$gatewayName = 'SagePay_Server',
		UserInterface $user,
		Request $request,
		Instance $cache,
		Order $order,
		$config
	) {
		$this->_user    = $user;
		$this->_request = $request;
		$this->_cache   = $cache;
		$this->_order   = $order;
		$this->setGateway($gatewayName, $request);
		$this->_config = $config;
	}

	public function setDeliveryAddress(Address $address)
	{
		parent::setDeliveryAddress($address);

		// Remove the state if the country is not United States
		if ("US" !== strtoupper($address->countryID)) {
			$this->_card->setShippingState("");
		}
	}

	public function setBillingAddress(Address $address)
	{
		parent::setBillingAddress($address);

		// Remove the state if the country is not United States
		if ("US" !== strtoupper($address->countryID)) {
			$this->_card->setState("");
		}
	}

	public function send()
	{
		$this->_transactionID = $this->_request->getSession()->getID().'_'.time();

		$this->_data = array(
			'amount'        => $this->_paymentAmount,
			'card'          => $this->_card,
			'currency'      => $this->_currencyID,
			'returnUrl'     => $this->_redirect,
			'transactionId' => $this->_transactionID,
			'description'   => 'Uniform Wares payment',
		);

		$this->_request = $this->_gateway->purchase($this->_data);

		$this->_response = $this->_request->send();

		return $this->_response;
	}

	public function completePurchase($data)
	{
		$request = $this->_gateway->completePurchase($data['requestData']);
		$request->setTransactionReference(json_encode($data['returnData']));
		$request->setTransactionId($data['returnData']['transactionId']);

		return $request->send();
	}

	public function saveResponse()
	{
		$data = $this->_response->getData();
		$data['transactionId'] = $this->_data['transactionId'];
		$data['VendorTxCode']  = $this->_data['transactionId'];

		$filename = $data['VPSTxId'];
    	$data 	  = serialize(array(
    		'returnData'  => $data,
    		'requestData' => $this->_data,
    		'order'		  => $this->_order,
    	));

    	$this->_cache->store($filename, $data);
	}

	public function handleResponse($responseID)
	{
		$data = $this->_cache->fetch($responseID);
		$this->_cache->delete($responseID);

		return unserialize($data);
	}

	public function refund(Payment $payment, $amount)
	{
		$this->setUsername($this->_config->checkout->payment->username);
		$this->_gateway->setTestMode($this->_config->checkout->payment->useTestPayments);

		$reference = json_decode($payment->reference);
		$reference->VPSTxId = str_replace(array('}','{'),'', $reference->VPSTxId);

		$values = array(
			'transactionId' => $payment->order->id.'_'.time(), // Needs to be unique
			'Amount'        => $amount, //
			'Currency'      => $payment->order->currencyID,
			'Description'   => 'Refund from '.$this->_config->app->name,
		);

		$values['transactionReference'] = json_encode($reference);
		$request = $this->_gateway->refund($values);

		$response = $request->send();

		if ($response->isSuccessful()) {
			return $response->getData();
		} else {
			$data = $response->getData();
			throw new \Exception($data['StatusDetail']);
		}
	}
}