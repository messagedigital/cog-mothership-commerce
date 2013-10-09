<?php

namespace Message\Mothership\Commerce\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class Routes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['ms.product']->setParent('ms.cp')->setPrefix('/product');
		$router['ms.order']->setParent('ms.cp')->setPrefix('/order');

		$router['ms.product']->add('ms.commerce.product.dashboard', '', '::Controller:Product:Dashboard#index');


		$router['ms.product']->add('ms.commerce.product.create.action', 'create', '::Controller:Product:Create#process')
			->setMethod('POST');
		$router['ms.product']->add('ms.commerce.product.create', 'create', '::Controller:Product:Create#index');

		$router['ms.product']->add('ms.commerce.product.delete', 'delete/{productID}', '::Controller:Product:Delete#delete')
			->setRequirement('productID', '\d+')
			->setMethod('DELETE');

		$router['ms.product']->add('ms.commerce.product.restore', 'restore/{productID}/{hash}', '::Controller:Product:Delete#restore')
			->setRequirement('productID', '\d+')
			->setMethod('GET')
			->enableCsrf('hash');


		$router['ms.product']->add('ms.commerce.product.edit.attributes.action', 'edit/{productID}', '::Controller:Product:Edit#processProductAttributes')
			->setRequirement('productID', '\d+')
			->setMethod('POST');
		$router['ms.product']->add('ms.commerce.product.edit.attributes', 'edit/{productID}', '::Controller:Product:Edit#productAttributes')
			->setRequirement('productID', '\d+');


		$router['ms.product']->add('ms.commerce.product.edit.details.action', 'edit/{productID}/details', '::Controller:Product:Edit#processProductDetails')
			->setRequirement('productID', '\d+')
			->setMethod('POST');
		$router['ms.product']->add('ms.commerce.product.edit.details', 'edit/{productID}/details', '::Controller:Product:Edit#productDetails')
			->setRequirement('productID', '\d+');


		$router['ms.product']->add('ms.commerce.product.edit.pricing.action', 'edit/{productID}/pricing', '::Controller:Product:Edit#processProductPricing')
			->setRequirement('productID', '\d+')
			->setMethod('POST');
		$router['ms.product']->add('ms.commerce.product.edit.pricing', 'edit/{productID}/pricing', '::Controller:Product:Edit#productPricing')
			->setRequirement('productID', '\d+');


		$router['ms.product']->add('ms.commerce.product.edit.units.action', 'edit/{productID}/units', '::Controller:Product:Edit#processUnit')
			->setRequirement('productID', '\d+')
			->setMethod('POST');
		$router['ms.product']->add('ms.commerce.product.edit.units.create.action', 'edit/{productID}/units/create', '::Controller:Product:Edit#processAddUnit')
			->setRequirement('productID', '\d+')
			->setMethod('POST');
		$router['ms.product']->add('ms.commerce.product.edit.units', 'edit/{productID}/units', '::Controller:Product:Edit#units')
			->setRequirement('productID', '\d+');

		$router['ms.product']->add('ms.commerce.product.unit.delete', 'edit/{productID}/units/delete/{unitID}', '::Controller:Product:Edit#deleteUnit')
			->setRequirement('productID', '\d+')
			->setRequirement('unitID', '\d+')
			->setMethod('GET')
			->enableCsrf('csrfHash');


		$router['ms.product']->add('ms.commerce.product.edit.stock.action', 'edit/{productID}/stock', '::Controller:Product:Edit#processStock')
			->setRequirement('productID', '\d+')
			->setMethod('POST');
		$router['ms.product']->add('ms.commerce.product.edit.stock', 'edit/{productID}/stock', '::Controller:Product:Edit#stock')
			->setRequirement('productID', '\d+');


		$router['ms.product']->add('ms.commerce.product.edit.images.action', 'edit/{productID}/images', '::Controller:Product:Edit#processImage')
			->setRequirement('productID', '\d+')
			->setMethod('POST');
		$router['ms.product']->add('ms.commerce.product.edit.images', 'edit/{productID}/images', '::Controller:Product:Edit#images')
			->setRequirement('productID', '\d+');


		$router['ms.product.basket']->add('ms.commerce.product.add.basket', '/basket/add/{productID}', '::Controller:Module:ProductSelector#process')
			->setRequirement('productID', '\d+')
			->setMethod('POST');


		$router['ms.order']->add('ms.commerce.order.view.dashboard', 'view', '::Controller:Order:Listing#dashboard');
		$router['ms.order']->add('ms.commerce.order.view.all', 'view/all', '::Controller:Order:Listing#all');
		$router['ms.order']->add('ms.commerce.order.view.shipped', 'view/shipped', '::Controller:Order:Listing#shipped');
		$router['ms.order']->add('ms.commerce.order.search.action', 'search', '::Controller:Order:Listing#searchAction')
			->setMethod('POST');

		$router['ms.order']->add('ms.commerce.order.detail.view', 'view/{orderID}', '::Controller:Order:OrderDetail#orderOverview')
			->setRequirement('orderID', '\d+');
		$router['ms.order']->add('ms.commerce.order.detail.view.items', 'view/{orderID}/item', '::Controller:Order:OrderDetail#itemListing')
			->setRequirement('orderID', '\d+');
		$router['ms.order']->add('ms.commerce.order.detail.view.addresses', 'view/{orderID}/address', '::Controller:Order:OrderDetail#addressListing')
			->setRequirement('orderID', '\d+');
		$router['ms.order']->add('ms.commerce.order.detail.view.payments', 'view/{orderID}/payment', '::Controller:Order:OrderDetail#paymentListing')
			->setRequirement('orderID', '\d+');
		$router['ms.order']->add('ms.commerce.order.detail.view.dispatches', 'view/{orderID}/dispatch', '::Controller:Order:OrderDetail#dispatchListing')
			->setRequirement('orderID', '\d+');
		$router['ms.order']->add('ms.commerce.order.detail.view.notes', 'view/{orderID}/note', '::Controller:Order:OrderDetail#noteListing')
			->setRequirement('orderID', '\d+');
		$router['ms.order']->add('ms.commerce.order.detail.view.documents', 'view/{orderID}/document', '::Controller:Order:OrderDetail#documentListing')
			->setRequirement('orderID', '\d+');

		$router['ms.order']->add('ms.commerce.order.detail.process.notes', 'process/{orderID}/note', '::Controller:Order:OrderDetail#processNote')
			->setRequirement('orderID', '\d+');

		$router['ms.order']->add('ms.commerce.order.document.print', 'document/{documentID}/print', '::Controller:Order:Document#printDocument');
	}
}