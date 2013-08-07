<?php

namespace Message\Mothership\Commerce\Bootstrap;

use Message\Mothership\Commerce;
use Message\Mothership\Commerce\Order\Statuses as OrderStatuses;

use Message\Cog\Bootstrap\ServicesInterface;

class Services implements ServicesInterface
{
	public function registerServices($services)
	{
		$services['order'] = function($c) {
			return new Commerce\Order\Order($c['order.entities']);
		};

		$services['basket'] = function($c) {
			if (!$c['http.session']->get('basket')) {
				$c['http.session']->set('basket',new Commerce\Order\Assembler(
					$c['order'],
					$c['user.current'],
					$c['locale'],
					$c['event.dispatcher'],
					$c['http.session']
				));
			}

			return $c['http.session']->get('basket');
		};

		$services['order.entities'] = function($c) {
			return array(
				'addresses'  => $c['order.address.loader'],
				'discounts'  => $c['order.discount.loader'],
				'dispatches' => $c['order.dispatch.loader'],
				'documents'  => $c['order.document.loader'],
				'items'      => $c['order.item.loader'],
				'notes'      => $c['order.note.loader'],
				'payments'   => $c['order.payment.loader'],
				'refunds'    => $c['order.refund.loader'],
			);
		};

		// Order decorators
		$services['order.loader'] = function($c) {
			return new Commerce\Order\Loader($c['db.query'], $c['user.loader'], $c['order.statuses'], $c['order.item.statuses'], $c['order.entities']);
		};

		$services['order.create'] = function($c) {
			return new Commerce\Order\Create(
				$c['db.transaction'],
				$c['order.loader'],
				$c['event.dispatcher'],
				$c['user.current'],
				array(
					'addresses' => $c['order.address.create'],
					'discounts' => $c['order.discount.create'],
					'items'     => $c['order.item.create'],
					'notes'     => $c['order.note.create'],
					'payments'  => $c['order.payment.create'],
				)
			);
		};

		$services['order.edit'] = function($c) {
			return new Commerce\Order\Edit(
				$c['db.query'],
				$c['event.dispatcher'],
				$c['order.statuses'],
				$c['user.current']
			);
		};

		// Order address entity
		$services['order.address.loader'] = function($c) {
			return new Commerce\Order\Entity\Address\Loader($c['db.query']);
		};

		$services['order.address.create'] = function($c) {
			return new Commerce\Order\Entity\Address\Create($c['db.query'], $c['user.current']);
		};

		// Order item entity
		$services['order.item.loader'] = function($c) {
			return new Commerce\Order\Entity\Item\Loader($c['db.query'], $c['order.item.status.loader']);
		};

		$services['order.item.create'] = function($c) {
			return new Commerce\Order\Entity\Item\Create($c['db.transaction'], $c['user.current']);
		};

		$services['order.item.edit'] = function($c) {
			return new Commerce\Order\Entity\Item\Edit($c['db.transaction'], $c['event.dispatcher'], $c['order.item.statuses'], $c['user.current']);
		};

		// Order discount entity
		$services['order.discount.loader'] = function($c) {
			return new Commerce\Order\Entity\Discount\Loader($c['db.query']);
		};

		$services['order.discount.create'] = function($c) {
			return new Commerce\Order\Entity\Discount\Create($c['db.transaction'], $c['user.current']);
		};

		// Order dispatch entity
		$services['order.dispatch.loader'] = function($c) {
			return new Commerce\Order\Entity\Dispatch\Loader($c['db.query'], $c['order.dispatch.methods']);
		};

		$services['order.dispatch.create'] = function($c) {
			return new Commerce\Order\Entity\Dispatch\Create($c['db.transaction'], $c['order.dispatch.loader'], $c['user.current']);
		};

		// Order document entity
		$services['order.document.loader'] = function($c) {
			return new Commerce\Order\Entity\Document\Loader($c['db.query']);
		};

		// Order item status
		$services['order.item.status.loader'] = function($c) {
			return new Commerce\Order\Entity\Item\Status\Loader($c['db.query'], $c['order.item.statuses']);
		};

		// Order payment entity
		$services['order.payment.loader'] = function($c) {
			return new Commerce\Order\Entity\Payment\Loader($c['db.query'], $c['order.payment.methods']);
		};

		$services['order.payment.create'] = function($c) {
			return new Commerce\Order\Entity\Payment\Create($c['db.transaction'], $c['user.current']);
		};

		// Order refund entity
		$services['order.refund.loader'] = function($c) {
			return new Commerce\Order\Entity\Refund\Loader($c['db.query'], $c['order.payment.methods']);
		};

		// Order note entity
		$services['order.note.loader'] = function($c) {
			return new Commerce\Order\Entity\Note\Loader($c['db.query']);
		};

		$services['order.note.create'] = function($c) {
			return new Commerce\Order\Entity\Note\Create($c['db.query'], $c['user.current']);
		};

		// Available payment & despatch methods
		$services['order.payment.methods'] = $services->share(function($c) {
			return new Commerce\Order\Entity\Payment\MethodCollection(array(
				new Commerce\Order\Entity\Payment\Method\Card,
				new Commerce\Order\Entity\Payment\Method\Cash,
				new Commerce\Order\Entity\Payment\Method\Cheque,
			));
		});

		$services['order.dispatch.methods'] = $services->share(function($c) {
			return new Commerce\Order\Entity\Dispatch\MethodCollection;
		});

		// Dispatch method selector
		$services['order.dispatch.method.selector'] = $services->share(function($c) {
			return new Commerce\Order\Entity\Dispatch\MethodSelector($c['order.dispatch.methods']);
		});

		// Available order & item statuses
		$services['order.statuses'] = $services->share(function($c) {
			return new Commerce\Order\Status\Collection(array(
				new Commerce\Order\Status\Status(OrderStatuses::AWAITING_DISPATCH,     'Awaiting Dispatch'),
				new Commerce\Order\Status\Status(OrderStatuses::PROCESSING,            'Processing'),
				new Commerce\Order\Status\Status(OrderStatuses::PARTIALLY_DISPATCHED,  'Partially Dispatched'),
				new Commerce\Order\Status\Status(OrderStatuses::PARTIALLY_RECEIVED,    'Partially Received'),
				new Commerce\Order\Status\Status(OrderStatuses::DISPATCHED,            'Dispatched'),
				new Commerce\Order\Status\Status(OrderStatuses::RECEIVED,              'Received'),
			));
		});

		$services['order.item.statuses'] = $services->share(function($c) {
			return new Commerce\Order\Status\Collection(array(
				new Commerce\Order\Status\Status(OrderStatuses::AWAITING_DISPATCH, 'Awaiting Dispatch'),
				new Commerce\Order\Status\Status(OrderStatuses::DISPATCHED,        'Dispatched'),
				new Commerce\Order\Status\Status(OrderStatuses::RECEIVED,          'Received'),
			));
		});

		// Product
		$services['product'] = function($c) {
			return new Commerce\Product\Product($c['locale'], $c['product.entities'], $c['product.price.types']);
		};

		$services['product.unit'] = function($c) {
			return new Commerce\Product\Unit\Unit($c['locale'], $c['product.price.types']);
		};

		$services['product.price.types'] = function($c) {
			return array(
				'retail',
				'rrp',
				'cost',
			);
		};

		$services['product.entities'] = function($c) {
			return array(
				'units' => $c['product.unit.loader'],
			);
		};

		$services['product.loader'] = function($c) {
			return new Commerce\Product\Loader(
				$c['db.query'],
				$c['locale'],
				$c['file_manager.file.loader'],
				$c['product.image.types'],
				$c['product.entities'],
				$c['product.price.types']
			);
		};

		$services['product.unit.loader'] = function($c) {
			return new Commerce\Product\Unit\Loader(
				$c['db.query'],
				$c['locale'],
				$c['product.price.types']
			);
		};

		$services['product.create'] = function($c) {
			return new Commerce\Product\Create($c['db.query'], $c['locale'], $c['user.current']);
		};

		$services['product.image.types'] = function($c) {
			return new Commerce\Product\ImageType\Collection(array(
				new Commerce\Product\ImageType\ImageType('default'),
			));
		};

		$services['product.edit'] = function($c) {
			return new Commerce\Product\Edit($c['db.query'], $c['locale'], $c['user.current']);
		};

		$services['product.unit.loader'] = function($c) {
			return new Commerce\Product\Unit\Loader($c['db.query'], $c['locale'], $c['product.price.types']);
		};

		$services['product.unit.edit'] = function($c) {
			return new Commerce\Product\Unit\Edit($c['db.query'], $c['product.unit.loader'], $c['user.current'], $c['locale']);
		};

		$services['product.unit.create'] = function($c) {
			return new Commerce\Product\Unit\Create($c['db.query'], $c['user.current'], $c['locale']);
		};

		$services['product.unit.delete'] = function($c) {
			return new Commerce\Product\Unit\Delete($c['db.query'], $c['user.current']);
		};

		$services['country.list'] = function($c) {
			return new Commerce\CountryList;
		};

		$services['option.loader'] = function($c) {
			return new Commerce\Product\OptionLoader($c['db.query'], $c['locale']);
		};

		$services['commerce.user.loader'] = function($c) {
			return new Commerce\User\Address\Loader($c['db.query']);
		};

		$services['commerce.user.collection'] = function($c) {
			return new Commerce\User\Collection($c['user.current'], $c['commerce.user.loader']);
		};

		$services['orders.entities.shipping.methods'] = $services->share(function($c) {
			return new Commerce\Order\Entity\Shipping\Method\MethodCollection;
		});
	}
}