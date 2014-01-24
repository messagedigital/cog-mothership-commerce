<?php

namespace Message\Mothership\Commerce\Gateway;

use Omnipay\SagePay\ServerGateway;

class SagePayServerGateway extends ServerGateway
{
	public function purchase(array $parameters = array())
    {
        return $this->createRequest('\Message\Mothership\Commerce\Gateway\SagePayServerAuthorizeRequest', $parameters);
    }
}