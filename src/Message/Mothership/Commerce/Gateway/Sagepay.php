<?php

namespace Message\Mothership\Commerce\Gateway;

use Message\User\UserInterface;
use Message\Cog\HTTP\Request;
use Message\Cog\Cache\Instance;
use Message\Mothership\Commerce\Order\Order;

class Sagepay extends Wrapper
{
	protected $_data;
	protected $_request;

	public function __construct($query, UserInterface $user, Request $request, Instance $cache, Order $order)
	{
		$this->_user    = $user;
		$this->_query   = $query;
		$this->_request = $request;
		$this->_cache   = $cache;
		$this->_order   = $order;
		$this->setGateway('Sagepay_Server', $request);
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

		return unserialize($data);
	}
}