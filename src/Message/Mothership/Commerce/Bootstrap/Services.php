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

		$services['order.entities'] = function($c) {
			return array(
				'addresses' => $c['order.address.loader'],
				'items'     => $c['order.item.loader'],
				'payments'  => $c['order.payment.loader'],
				'notes'     => $c['order.note.loader'],
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
					'items'     => $c['order.item.create'],
				)
			);
		};

		// Order address entity
		$services['order.address.loader'] = function($c) {
			return new Commerce\Order\Entity\Address\Loader($c['db.query']);
		};

		$services['order.address.create'] = function($c) {
			return new Commerce\Order\Entity\Address\Create($c['db.query']);
		};

		// Order item entity
		$services['order.item.loader'] = function($c) {
			return new Commerce\Order\Entity\Item\Loader($c['db.query'], $c['order.item.status.loader']);
		};

		$services['order.item.create'] = function($c) {
			return new Commerce\Order\Entity\Item\Create($c['db.transaction'], $c['user.current']);
		};

		$services['order.item.edit'] = function($c) {
			return new Commerce\Order\Entity\Item\Edit($c['db.query'], $c['order.item.statuses'], $c['user.current']);
		};

		// Order item status
		$services['order.item.status.loader'] = function($c) {
			return new Commerce\Order\Entity\Item\Status\Loader($c['db.query'], $c['order.item.statuses']);
		};

		// Order payment entity
		$services['order.payment.loader'] = function($c) {
			return new Commerce\Order\Entity\Payment\Loader($c['db.query'], $c['order.payment.methods']);
		};

		// Order note entity
		$services['order.note.loader'] = function($c) {
			return new Commerce\Order\Entity\Note\Loader($c['db.query']);
		};

		// Available payment & despatch methods
		$services['order.payment.methods'] = $services->share(function($c) {
			return new Commerce\Order\Entity\Payment\MethodCollection(array(
				new Commerce\Order\Entity\Payment\Method\Card,
				new Commerce\Order\Entity\Payment\Method\Cash,
				new Commerce\Order\Entity\Payment\Method\Cheque,
			));
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
				'unit' => $c['product.unit.loader'],
			);
		};

		$services['product.loader'] = function($c) {
			return new Commerce\Product\Loader(
				$c['db.query'],
				$c['locale'],
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
			return new Commerce\Product\Unit\Create($c['db.transaction'], $c['user.current']);
		};

		$services['country.list'] = function($c) {
			return new Commerce\CountryList;
		};

		$services['option.loader'] = function($c) {
			return new Commerce\Product\OptionLoader($c['db.query'], $c['locale']);
		};
	}
}