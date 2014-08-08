<?php

namespace Message\Mothership\Commerce\Bootstrap;

use Message\Mothership\Commerce;
use Message\Mothership\Commerce\Order\Statuses as OrderStatuses;
use Message\Mothership\Commerce\Order\Transaction\Types as TransactionTypes;

use Message\User\AnonymousUser;

use Message\Cog\Bootstrap\ServicesInterface;

class Services implements ServicesInterface
{
	public function registerServices($services)
	{
		$this->registerEmails($services);
		$this->registerProductPageMapper($services);
		$this->registerStatisticsDatasets($services);

		$services['order'] = $services->factory(function($c) {
			$order = new Commerce\Order\Order($c['order.entities']);
			$order->taxable = true; // default orders to taxable

			return $order;
		});

		$services->extend('form.factory.builder', function($factory, $c) {
			$factory->addExtension(new Commerce\Form\Extension\CommerceExtension(['GBP']));

			return $factory;
		});

		$services['basket.order'] = $services->factory(function($c) {
			if (!$c['http.session']->get('basket.order')) {
				$order             = $c['order'];
				$order->locale     = $c['locale']->getId();
				$order->currencyID = 'GBP';
				$order->type       = 'web';

				if ($c['user.current']
				&& !($c['user.current'] instanceof AnonymousUser)) {
					$order->user = $c['user.current'];
				}

				$c['http.session']->set('basket.order', $order);
			}

			return $c['http.session']->get('basket.order');
		});

		$services['basket'] = $services->factory(function($c) {
			$assembler = $c['order.assembler'];

			$assembler->setOrder($c['basket.order']);

			return $assembler;
		});

		$services['order.entities'] = $services->factory(function($c) {
			return array(
				'addresses'  => new Commerce\Order\Entity\CollectionOrderLoader(
					new Commerce\Order\Entity\Address\Collection,
					new Commerce\Order\Entity\Address\Loader($c['db.query'])
				),
				'discounts'  => new Commerce\Order\Entity\CollectionOrderLoader(
					new Commerce\Order\Entity\Discount\Collection,
					new Commerce\Order\Entity\Discount\Loader($c['db.query'])
				),
				'dispatches' => new Commerce\Order\Entity\CollectionOrderLoader(
					new Commerce\Order\Entity\Collection,
					new Commerce\Order\Entity\Dispatch\Loader($c['db.query'], $c['order.dispatch.methods'])
				),
				'documents'  => new Commerce\Order\Entity\CollectionOrderLoader(
					new Commerce\Order\Entity\Collection,
					new Commerce\Order\Entity\Document\Loader($c['db.query'])
				),
				'items'      => new Commerce\Order\Entity\CollectionOrderLoader(
					new Commerce\Order\Entity\Item\Collection,
					new Commerce\Order\Entity\Item\Loader(
						$c['db.query'],
						$c['order.item.status.loader'],
						$c['stock.locations'],
						new EntityLoaderCollection([
							'product' => $c['product.loader'],
							'unit'    => $c['product.unit.loader'],
						])
					)
				),
				'notes'      => new Commerce\Order\Entity\CollectionOrderLoader(
					new Commerce\Order\Entity\Collection,
					new Commerce\Order\Entity\Note\Loader($c['db.query'], $c['event.dispatcher'])
				),
				'payments'   => new Commerce\Order\Entity\CollectionOrderLoader(
					new Commerce\Order\Entity\Collection,
					new Commerce\Order\Entity\Payment\Loader($c['db.query'], $c['payment.loader'])
				),
				'refunds'    => new Commerce\Order\Entity\CollectionOrderLoader(
					new Commerce\Order\Entity\Collection,
					new Commerce\Order\Entity\Refund\Loader($c['db.query'], $c['refund.loader'])
				),
			);
		});

		$services['order.assembler'] = $services->factory(function($c) {
			$order = $c['order'];

			$order->locale     = $c['locale']->getId();
			$order->currencyID = 'GBP';

			$assembler = new Commerce\Order\Assembler(
				$order,
				$c['event.dispatcher'],
				$c['stock.locations']->getRoleLocation($c['stock.locations']::SELL_ROLE)
			);

			$assembler->setEntityTemporaryIdProperty('addresses', 'type');
			$assembler->setEntityTemporaryIdProperty('discounts', 'code');

			return $assembler;
		});

		// Order decorators
		$services['order.loader'] = $services->factory(function($c) {
			return new Commerce\Order\Loader(
				$c['db.query'],
				$c['user.loader'],
				$c['order.statuses'],
				$c['order.item.statuses'],
				$c['order.entities']
			);
		});

		$services['order.create'] = $services->factory(function($c) {
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
					'refunds'   => $c['order.refund.create'],
				)
			);
		});

		$services['order.delete'] = $services->factory(function($c) {
			return new Commerce\Order\Delete($c['db.transaction'], $c['event.dispatcher'], $c['user.current']);
		});

		$services['order.edit'] = $services->factory(function($c) {
			return new Commerce\Order\Edit(
				$c['db.transaction'],
				$c['event.dispatcher'],
				$c['order.statuses'],
				$c['user.current']
			);
		});

		// Order address entity
		$services['order.address.loader'] = $services->factory(function($c) {
			return $c['order.loader']->getEntityLoader('addresses');
		});

		$services['order.address.create'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Address\Create($c['db.query'], $c['order.address.loader'], $c['user.current']);
		});

		// Order item entity
		$services['order.item.loader'] = $services->factory(function($c) {
			return $c['order.loader']->getEntityLoader('items');
		});

		$services['order.item.create'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Item\Create($c['db.transaction'], $c['order.item.loader'], $c['event.dispatcher'], $c['user.current']);
		});

		$services['order.item.delete'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Item\Delete($c['db.query'], $c['user.current']);
		});

		$services['order.item.edit'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Item\Edit($c['db.transaction'], $c['event.dispatcher'], $c['order.item.statuses'], $c['user.current']);
		});

		// Order discount entity
		$services['order.discount.loader'] = $services->factory(function($c) {
			return $c['order.loader']->getEntityLoader('discounts');
		});

		$services['order.discount.create'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Discount\Create($c['db.query'], $c['order.discount.loader'], $c['user.current']);
		});

		// Order dispatch entity
		$services['order.dispatch.loader'] = $services->factory(function($c) {
			return $c['order.loader']->getEntityLoader('dispatches');
		});

		$services['order.dispatch.create'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Dispatch\Create($c['db.transaction'], $c['order.dispatch.loader'], $c['user.current']);
		});

		$services['order.dispatch.edit'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Dispatch\Edit($c['db.query'], $c['user.current'], $c['event.dispatcher']);
		});

		// Order document entity
		$services['order.document.loader'] = $services->factory(function($c) {
			return $c['order.loader']->getEntityLoader('documents');
		});

		$services['order.document.create'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Document\Create(
				$c['db.query'],
				$c['order.document.loader'],
				$c['user.current']
			);
		});

		// Order item status
		$services['order.item.status.loader'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Item\Status\Loader($c['db.query'], $c['order.item.statuses']);
		});

		// Order payment entity
		$services['order.payment.loader'] = $services->factory(function($c) {
			return $c['order.loader']->getEntityLoader('payments');
		});

		$services['order.payment.create'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Payment\Create(
				$c['db.transaction'],
				$c['payment.create'],
				$c['order.payment.loader'],
				$c['event.dispatcher']
			);
		});

		$services['order.payment.delete'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Payment\Delete($c['payment.delete']);
		});

		// Order refund entity
		$services['order.refund.loader'] = $services->factory(function($c) {
			return $c['order.loader']->getEntityLoader('refunds');
		});

		$services['order.refund.create'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Refund\Create(
				$c['db.transaction'],
				$c['refund.create'],
				$c['order.refund.loader'],
				$c['event.dispatcher']
			);
		});

		$services['order.refund.delete'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Refund\Delete($c['refund.delete']);
		});

		// Order note entity
		$services['order.note.loader'] = $services->factory(function($c) {
			return $c['order.loader']->getEntityLoader('notes');
		});

		$services['order.note.create'] = $services->factory(function($c) {
			return new Commerce\Order\Entity\Note\Create(
				$c['db.query'],
				$c['order.note.loader'],
				$c['user.current'],
				$c['event.dispatcher']);
		});

		// Available despatch methods
		$services['order.dispatch.methods'] = function($c) {
			return new Commerce\Order\Entity\Dispatch\MethodCollection;
		};

		// Dispatch method selector
		$services['order.dispatch.method.selector'] = function($c) {
			return new Commerce\Order\Entity\Dispatch\MethodSelector($c['order.dispatch.methods']);
		};

		// Available order & item statuses
		$services['order.statuses'] = function($c) {
			return new Commerce\Order\Status\Collection(array(
				new Commerce\Order\Status\Status(OrderStatuses::PAYMENT_PENDING,      'Payment Pending'),
				new Commerce\Order\Status\Status(OrderStatuses::CANCELLED,            'Cancelled'),
				new Commerce\Order\Status\Status(OrderStatuses::AWAITING_DISPATCH,    'Awaiting Dispatch'),
				new Commerce\Order\Status\Status(OrderStatuses::PROCESSING,           'Processing'),
				new Commerce\Order\Status\Status(OrderStatuses::PARTIALLY_DISPATCHED, 'Partially Dispatched'),
				new Commerce\Order\Status\Status(OrderStatuses::PARTIALLY_RECEIVED,   'Partially Received'),
				new Commerce\Order\Status\Status(OrderStatuses::DISPATCHED,           'Dispatched'),
				new Commerce\Order\Status\Status(OrderStatuses::RECEIVED,             'Received'),
			));
		};

		$services['order.item.statuses'] = function($c) {
			return new Commerce\Order\Status\Collection(array(
				new Commerce\Order\Status\Status(OrderStatuses::CANCELLED,         'Cancelled'),
				new Commerce\Order\Status\Status(OrderStatuses::AWAITING_DISPATCH, 'Awaiting Dispatch'),
				new Commerce\Order\Status\Status(OrderStatuses::DISPATCHED,        'Dispatched'),
				new Commerce\Order\Status\Status(OrderStatuses::RECEIVED,          'Received'),
			));
		};

		// Configurable/optional event listeners
		$services['order.listener.vat'] = $services->factory(function($c) {
			return new Commerce\Order\EventListener\VatListener($c['country.list']);
		});

		$services['order.listener.assembler.stock_check'] = function($c) {
			return new Commerce\Order\EventListener\Assembler\StockCheckListener('web');
		};

		// Available transaction types
		$services['order.transaction.types'] = function($c) {
			return array(
				TransactionTypes::ORDER               => 'Order',
				TransactionTypes::CONTRACT_INITIATION => 'Contract Initiation',
				TransactionTypes::CONTRACT_PAYMENT    => 'Contract Payment',
			);
		};

		$services['order.transaction.loader'] = function($c) {
			$orderLoader        = $c['order.loader'];
			$itemLoader         = $c['order.item.loader'];
			$paymentLoader      = $c['payment.loader'];
			$refundLoader       = $c['refund.loader'];
			$orderPaymentLoader = $c['order.payment.loader'];
			$orderRefundLoader  = $c['order.refund.loader'];

			$orderLoader->includeDeleted(true);
			$itemLoader->includeDeleted(true);
			$paymentLoader->includeDeleted(true);
			$refundLoader->includeDeleted(true);
			$orderPaymentLoader->includeDeleted(true);
			$orderRefundLoader->includeDeleted(true);

			return new Commerce\Order\Transaction\Loader($c['db.query'], array(
				Commerce\Order\Order::RECORD_TYPE                  => $orderLoader,
				Commerce\Order\Entity\Item\Item::RECORD_TYPE       => $itemLoader,
				Commerce\Refund\Refund::RECORD_TYPE                => $refundLoader,
				Commerce\Payment\Payment::RECORD_TYPE              => $paymentLoader,
				Commerce\Order\Entity\Refund\Refund::RECORD_TYPE   => $orderRefundLoader,
				Commerce\Order\Entity\Payment\Payment::RECORD_TYPE => $orderPaymentLoader,
			));
		};

		$services['order.transaction.create'] = function($c) {
			return new Commerce\Order\Transaction\Create($c['db.transaction'], $c['order.transaction.loader'], $c['event.dispatcher'], $c['user.current']);
		};

		$services['order.transaction.edit'] = function($c) {
			return new Commerce\Order\Transaction\Edit($c['db.transaction'], $c['user.current']);
		};

		$services['order.transaction.void'] = function($c) {
			return new Commerce\Order\Transaction\Void(
				$c['db.transaction'],
				$c['event.dispatcher'],
				$c['user.current']
			);
		};

		// Product
		$services['product'] = $services->factory(function($c) {
			return new Commerce\Product\Product($c['locale'], $c['product.entities'], $c['product.price.types']);
		});

		$services['product.unit'] = $services->factory(function($c) {
			return new Commerce\Product\Unit\Unit($c['locale'], $c['product.price.types']);
		});

		$services['product.price.types'] = function($c) {
			return array(
				'retail',
				'rrp',
				'cost',
			);
		};

		$services['product.entities'] = function($c) {
			return array(
				'units' => new Commerce\Product\Unit\Loader(
					$c['db.query'],
					$c['locale'],
					$c['product.price.types']
				),
			);
		};

		$services['product.loader'] = $services->factory(function($c) {
			return new Commerce\Product\Loader(
				$c['db.query'],
				$c['locale'],
				$c['file_manager.file.loader'],
				$c['product.types'],
				$c['product.detail.loader'],
				$c['product.entities'],
				$c['product.price.types']
			);
		});

		$services['product.searcher'] = $services->factory(function($c) {
			return new Commerce\Product\Searcher(
				$c['db.query'],
				$c['product.loader'],
				3
			);
		});

		$services['product.create'] = $services->factory(function($c) {
			$create = new Commerce\Product\Create($c['db.query'], $c['locale'], $c['user.current']);

			$create->setDefaultTaxStrategy($c['cfg']->product->defaultTaxStrategy);

			return $create;
		});

		$services['product.delete'] = $services->factory(function($c) {
			return new Commerce\Product\Delete($c['db.query'], $c['user.current']);
		});

		$services['product.image.types'] = function($c) {
			return new Commerce\Product\Image\TypeCollection(array(
				'default' => 'Default',
			));
		};

		$services['product.image.create'] = $services->factory(function($c) {
			return new Commerce\Product\Image\Create($c['db.transaction'], $c['user.current']);
		});

		$services['product.unit.loader'] = $services->factory(function($c) {
			return $c['product.loader']->getEntityLoader('units');
		});

		$services['product.edit'] = $services->factory(function($c) {
			return new Commerce\Product\Edit($c['db.transaction'], $c['locale'], $c['user.current']);
		});

		$services['product.unit.edit'] = $services->factory(function($c) {
			return new Commerce\Product\Unit\Edit($c['db.query'], $c['product.unit.loader'], $c['user.current'], $c['locale']);
		});

		$services['product.unit.create'] = $services->factory(function($c) {
			return new Commerce\Product\Unit\Create($c['db.query'], $c['user.current'], $c['locale']);
		});

		$services['product.unit.delete'] = $services->factory(function($c) {
			return new Commerce\Product\Unit\Delete($c['db.query'], $c['user.current']);
		});

		$services->extend('field.collection', function($fields, $c) {
			$fields->add(new \Message\Mothership\Commerce\FieldType\Product($c['product.loader'], $c['commerce.field.product_list']));
			$fields->add(new \Message\Mothership\Commerce\FieldType\Productoption($c['product.option.loader']));

			return $fields;
		});

		$services['commerce.field.product_list'] = function($c) {
			return new \Message\Mothership\Commerce\FieldType\Helper\ProductList($c['db.query']);
		};

		// DO NOT USE: LEFT IN FOR BC
		$services['option.loader'] = $services->factory(function($c) {
			return $c['product.option.loader'];
		});

		$services['product.tax.rates'] = function($c) {
			return array(
				'20.00' => 'VAT - 20%'
			);
		};

		$services['product.option.loader'] = $services->factory(function($c) {
			return new Commerce\Product\OptionLoader($c['db.query'], $c['locale']);
		});

		$services['product.category.loader'] = $services->factory(function($c) {
			return new Commerce\Product\Category\Loader($c['db.query']);
		});

		$services['product.types'] = function($c) {
			return new Commerce\Product\Type\Collection(array(
				new Commerce\Product\Type\BasicProductType(),
			));
		};

		$services['product.form.attributes'] = $services->factory(function($c) {
			return new Commerce\Product\Form\ProductAttributes($c);
		});


		$services['product.form.search'] = $services->factory(function($c) {
			return new Commerce\Product\Form\ProductSearch($c['translator']);
		});

		$services['product.form.barcode'] = $services->factory(function($c) {
			return new Commerce\Product\Form\Barcode($c['stock.locations']);
		});

		$services['product.detail.loader'] = function($c) {
			return new Commerce\Product\Type\DetailLoader(
				$c['db.query'],
				$c['field.factory'],
				$c['product.types']
			);
		};

		$services['product.detail.edit'] = function($c) {
			return new Commerce\Product\Type\DetailEdit(
				$c['db.transaction'],
				$c['event.dispatcher'],
				$c['user.current']
			);
		};

		$services['product.barcode.generate'] = function($c) {
			return new Commerce\Product\Barcode\Generate(
				$c['db.query'],
				new Commerce\Product\Barcode\ImageResource,
				$c['product.barcode.sheet']->getBarcodeHeight(),
				$c['product.barcode.sheet']->getBarcodeWidth(),
				$c['cfg']->barcode->fileType,
				$c['cfg']->barcode->barcodeType
			);
		};

		$services['product.barcode.sheet.collection'] = function($c) {
			$collection = new Commerce\Product\Barcode\Sheet\Collection;
			$collection->add(new Commerce\Product\Barcode\Sheet\Size5x13);

			return $collection;
		};

		$services['product.barcode.sheet'] = function($c) {
			return $c['product.barcode.sheet.collection']->get(
				$c['cfg']->barcode->sheetType
			);
		};

		$services['commerce.user.address.loader'] = $services->factory(function($c) {
			return new Commerce\User\Address\Loader(
				$c['db.query'],
				$c['country.list'],
				$c['state.list']
			);
		});

		$services['commerce.user.address.create'] = $services->factory(function($c) {
			return new Commerce\User\Address\Create($c['db.query'], $c['commerce.user.address.loader'], $c['user.current']);
		});

		$services['commerce.user.address.edit'] = $services->factory(function($c) {
			return new Commerce\User\Address\Edit($c['db.query'], $c['user.current']);
		});

		$services['stock.manager'] = $services->factory(function($c) {
			$trans = $c['db.transaction'];
			return new Commerce\Product\Stock\StockManager(
				$trans,
				new Commerce\Product\Stock\Movement\Create(
					$trans,
					$c['user.current'],
					new Commerce\Product\Stock\Movement\Adjustment\Create($trans)
				),
				new Commerce\Product\Stock\Movement\Adjustment\Create($trans),
				$c['product.unit.edit'],
				$c['event.dispatcher']
			);
		});

		$services['stock.locations'] = function() {
			return new Commerce\Product\Stock\Location\Collection;
		};

		$services['stock.movement.loader'] = $services->factory(function($c) {
			return new Commerce\Product\Stock\Movement\Loader(
				$c['db.query'],
				new Commerce\Product\Stock\Movement\Adjustment\Loader(
					$c['db.query'],
					$c['product.unit.loader'],
					$c['stock.locations']
				),
				$c['stock.movement.reasons']
			);
		});

		$services['stock.movement.reasons'] = function() {
			return new Commerce\Product\Stock\Movement\Reason\Collection([
				new Commerce\Product\Stock\Movement\Reason\Reason('new_order', 'New Order'),
				new Commerce\Product\Stock\Movement\Reason\Reason('void_transaction', 'Voided transaction'),
				new Commerce\Product\Stock\Movement\Reason\Reason(
					\Message\Mothership\Commerce\Task\Stock\Barcode::REASON,
					'Stock Take'
				),
			]);
		};

		$services['stock.movement.iterator'] = $services->factory(function($c) {
			return new Commerce\Product\Stock\Movement\Iterator(
				$c['stock.movement.loader'],
				$c['stock.locations']
			);
		});

		$services['shipping.methods'] = function($c) {
			return new Commerce\Shipping\MethodCollection;
		};

		$services['forex'] = function($c) {
			return new Commerce\Forex\Forex(
				$c['db.query'],
				'GBP',
				array('GBP', 'USD', 'EUR', 'JPY')
			);
		};

		$services['forex.feed'] = function($c) {
			return new Commerce\Forex\Feed\ECB($c['db.query']);
		};

		/*
		 * Basket
		 */
		$services['order.basket.create'] = $services->factory(function($c) {
			return new Commerce\Order\Basket\Create($c['db.query']);
		});

		$services['order.basket.edit'] = $services->factory(function($c) {
			return new Commerce\Order\Basket\Edit($c['db.query']);
		});

		$services['order.basket.delete'] = $services->factory(function($c) {
			return new Commerce\Order\Basket\Delete($c['db.query']);
		});

		$services['order.basket.loader'] = function($c) {
			return new Commerce\Order\Basket\Loader($c['db.query'], $c['order.basket.token']);
		};

		$services['order.basket.token'] = $services->factory(function($c) {
			return new Commerce\Order\Basket\Token($c['user.password_hash'], $c['cfg']);
		});
	}

	public function registerEmails($services)
	{
		$services['mail.factory.order.note.notification'] = $services->factory(function($c) {
			$factory = new \Message\Cog\Mail\Factory($c['mail.message']);

			$factory->requires('order', 'note');

			$appName = $c['cfg']->app->name;

			$factory->extend(function($factory, $message) use ($appName) {
				$message->setTo($factory->order->user->email, $factory->order->user->getName());
				$message->setSubject(sprintf('Updates to your %s order - %d', $appName, $factory->order->orderID));
				$message->setView('Message:Mothership:Commerce::mail:order:note:customer-notification', array(
					'order' => $factory->order,
					'note'  => $factory->note,
				));
			});

			return $factory;
		});
	}

	public function registerProductPageMapper($services)
	{
		// Service to map pages to products and vice-versa
		$services['product.page_mapper.simple'] = function($c) {
			$mapper = new \Message\Mothership\Commerce\ProductPageMapper\SimpleMapper(
				$c['db.query'],
				$c['cms.page.loader'],
				$c['cms.page.authorisation'],
				$c['product.loader'],
				$c['product.unit.loader']
			);

			$mapper->setValidFieldNames('product');
			$mapper->setValidGroupNames(null);
			$mapper->setValidPageTypes('product');

			return $mapper;
		};

		$services['product.page_mapper.option_criteria'] = function($c) {
			$mapper = new \Message\Mothership\Commerce\ProductPageMapper\OptionCriteriaMapper(
				$c['db.query'],
				$c['cms.page.loader'],
				$c['cms.page.authorisation'],
				$c['product.loader'],
				$c['product.unit.loader']
			);

			$mapper->setValidFieldNames('product');
			$mapper->setValidGroupNames(null);
			$mapper->setValidPageTypes('product');

			return $mapper;
		};

		// Set the default product page mapper to the simple mapper
		$services['product.page_mapper'] = $services->raw('product.page_mapper.simple');
		$services['page.product_mapper'] = $services->raw('product.page_mapper.simple');

		// Extend twig with the product/page finders
		$services->extend('templating.twig.environment', function($twig, $c) {
			$twig->addExtension(new \Message\Mothership\Commerce\ProductPageMapper\Templating\TwigExtension(
				$c['page.product_mapper'],
				$c['product.page_mapper']
			));

			return $twig;
		});
	}

	public function registerStatisticsDatasets($services)
	{
		$services->extend('statistics', function($statistics, $c) {
			$statistics->add(new Commerce\Statistic\OrdersIn     ($c['db.query'], $c['statistics.counter'], $c['statistics.range.date']));
			$statistics->add(new Commerce\Statistic\OrdersOut    ($c['db.query'], $c['statistics.counter'], $c['statistics.range.date']));
			$statistics->add(new Commerce\Statistic\SalesNet     ($c['db.query'], $c['statistics.counter'], $c['statistics.range.date']));
			$statistics->add(new Commerce\Statistic\SalesGross   ($c['db.query'], $c['statistics.counter'], $c['statistics.range.date']));
			$statistics->add(new Commerce\Statistic\ProductsSales($c['db.query'], $c['statistics.counter.key'], $c['statistics.range.date']));

			return $statistics;
		});
	}
}
