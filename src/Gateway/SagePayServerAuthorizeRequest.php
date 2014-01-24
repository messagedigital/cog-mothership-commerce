<?php

namespace Message\Mothership\Commerce\Gateway;

use Omnipay\SagePay\Message\ServerAuthorizeRequest;

class SagePayServerAuthorizeRequest extends ServerAuthorizeRequest
{
	public function getData()
	{
		$data = parent::getData();

		$data['RedirectUrl'] = $this->getRedirectUrl();

		return $data;
	}
}