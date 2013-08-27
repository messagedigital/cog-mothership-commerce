<?php

namespace Message\Mothership\Commerce\Controller\Product;

use Message\Cog\Controller\Controller;

class Menu extends Controller
{
	public function index($productID = null)
	{
		return $this->render('Message:Mothership:Commerce::product:menu', array(
			'productID' => $productID,
		));
	}
}