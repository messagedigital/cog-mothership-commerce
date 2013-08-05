<?php

namespace Message\Mothership\Commerce\Controller\Order;

use Message\Cog\Controller\Controller;
use Message\Cog\ValueObject\DateTimeImmutable;

class Tabs extends Controller
{

	public function create($orderID)
	{
		$data = array('orderID' => $orderID);
		$tabs = array(
			$this->trans('ms.commerce.order.order-overview.title')		=> 	$this->generateUrl('ms.commerce.order.detail.view.order-overview', 	$data),
			$this->trans('ms.commerce.order.item.items.title')   		=>	$this->generateUrl('ms.commerce.order.detail.view.items', 			$data),
			$this->trans('ms.commerce.order.address.addresses.title')   =>	$this->generateUrl('ms.commerce.order.detail.view.addresses', 		$data),
			$this->trans('ms.commerce.order.payment.payments.title')   	=>	$this->generateUrl('ms.commerce.order.detail.view.payments', 		$data),
			$this->trans('ms.commerce.order.dispatch.dispatches.title') =>	$this->generateUrl('ms.commerce.order.detail.view.dispatches', 		$data),
			$this->trans('ms.commerce.order.note.notes.title') 			=>	$this->generateUrl('ms.commerce.order.detail.view.notes',	 		$data),
		);

		$current = ucfirst(trim(strrchr($this->get('http.request.master')->get('_controller'), '::'), ':'));
		return $this->render('Message:Mothership:Commerce::order:detail:tabs', array(
			'tabs'    => $tabs,
			'current' => $current,
		));
	}

}
	