<?php

namespace Message\Mothership\Commerce\Order;

use Message\User\UserInterface;

use Message\Cog\DB;
use Message\Cog\Event\DispatcherInterface;
use Message\Cog\ValueObject\Authorship;
use Message\Cog\ValueObject\DateTimeImmutable;

/**
 * Order creator.
 *
 * Creates order with shipping, and delegates the creation of any entities
 * attached to the order to the defined entity creators.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Create
{
	protected $_trans;
	protected $_loader;
	protected $_eventDispatcher;
	protected $_currentUser;

	protected $_entityCreators = array();

	public function __construct(DB\Transaction $trans, Loader $loader,
		DispatcherInterface $eventDispatcher, UserInterface $user, array $entityCreators = array())
	{
		$this->_trans           = $trans;
		$this->_loader          = $loader;
		$this->_eventDispatcher = $eventDispatcher;
		$this->_currentUser     = $user;

		foreach ($entityCreators as $name => $creator) {
			$this->addEntityCreator($name, $creator);
		}
	}

	/**
	 * Overwrite the current user, this should be used for when a user is being
	 * placed via sagepay and the request is coming from a different server.
	 *
	 * @param UserInterface $user user to set when the order is created
	 */
	public function setUser(UserInterface $user)
	{
		$this->_currentUser = $user;

		return $this;
	}

	/**
	 * Define the creator for an entity type.
	 *
	 * @param string                    $name    Entity name
	 * @param DB\TransactionalInterface $creator Entity creator
	 *
	 * @throws \InvalidArgumentException If an entity creator with the given
	 *                                   name already exists
	 */
	public function addEntityCreator($name, DB\TransactionalInterface $creator)
	{
		if (array_key_exists($name, $this->_entityCreators)) {
			throw new \InvalidArgumentException(sprintf('Order entity creator already exists with name `%s`', $name));
		}

		$creator->setTransaction($this->_trans);

		$this->_entityCreators[$name] = $creator;
	}

	public function create(Order $order, array $metadata = array())
	{
		$event = new Event\TransactionalEvent($order);
		$event->setTransaction($this->_trans);
		$order = $this->_eventDispatcher->dispatch(Events::CREATE_START, $event)
			->getOrder();

		$validation = $this->_eventDispatcher->dispatch(Events::CREATE_VALIDATE, new Event\ValidateEvent($order));

		if ($validation->hasErrors()) {
			throw new \InvalidArgumentException(sprintf('Cannot create order: %s', implode(', ', $validation->getErrors())));
		}

		if (!$order->authorship->createdAt()) {
			$order->authorship->create(
				new DateTimeImmutable,
				$this->_currentUser->id
			);
		}

		$this->_trans->add('
			INSERT INTO
				order_summary
			SET
				created_at       = :createdAt?d,
				created_by       = :createdBy?in,
				status_code      = :status?i,
				user_id          = :userID?in,
				user_email		 = :userEmail?sn,
				type             = :type?sn,
				locale           = :locale?s,
				taxable          = :taxable?b,
				currency_id      = :currencyID?s,
				conversion_rate  = :conversionRate?f,
				product_net      = :productNet?f,
				product_discount = :productDiscount?f,
				product_tax      = :productTax?f,
				product_gross    = :productGross?f,
				total_net        = :totalNet?f,
				total_discount   = :totalDiscount?f,
				total_tax        = :totalTax?f,
				total_gross      = :totalGross?f
		', array(
			'createdAt'       => $order->authorship->createdAt(),
			'createdBy'       => $order->authorship->createdBy(),
			'userID'          => $order->user ? $order->user->id : null,
			'userEmail'		  => $order->user ? $order->user->email : null,
			'status'          => $order->status->code,
			'type'            => $order->type,
			'locale'          => $order->locale,
			'taxable'         => $order->taxable,
			'currencyID'      => $order->currencyID,
			'conversionRate'  => $order->conversionRate,
			'productNet'      => $order->productNet,
			'productDiscount' => $order->productDiscount,
			'productTax'      => $order->productTax,
			'productGross'    => $order->productGross,
			'totalNet'        => $order->totalNet,
			'totalDiscount'   => $order->totalDiscount,
			'totalTax'        => $order->totalTax,
			'totalGross'      => $order->totalGross,
		));

		$this->_trans->setIDVariable('ORDER_ID');
		$order->id = '@ORDER_ID';

		$this->_trans->add('
			INSERT INTO
				order_shipping
			SET
				order_id     = :orderID?i,
				list_price   = :listPrice?f,
				net          = :net?f,
				discount     = :discount?f,
				tax          = :tax?f,
				tax_rate     = :taxRate?f,
				gross        = :gross?f,
				name         = :name?sn,
				display_name = :display_name?sn
		', array(
			'orderID'      => $order->id,
			'listPrice'    => $order->shippingListPrice,
			'net'          => $order->shippingNet,
			'discount'     => $order->shippingDiscount,
			'tax'          => $order->shippingTax,
			'taxRate'      => $order->shippingTaxRate,
			'gross'        => $order->shippingGross,
			'name'         => $order->shippingName,
			'display_name' => $order->shippingDisplayName
		));

		// Insert metadata
		foreach ($order->metadata as $key => $value) {
			$this->_trans->add('
				INSERT INTO
					order_metadata
				SET
					`order_id` = :orderID?i,
					`key`      = :key?s,
					`value`    = :value?sn
			', array(
				'orderID' => $order->id,
				'key'     => $key,
				'value'   => $value,
			));
		}

		foreach ($order->getEntities() as $name => $collection) {
			if (count($collection) > 0 && !array_key_exists($name, $this->_entityCreators)) {
				throw new \LogicException(sprintf('Creator for `%s` order entity not set on order creator', $name));
			}

			foreach ($collection as $entity) {
				$entity->order = $order;

				// Create the entities with the same authorship data as the order
				if (isset($entity->authorship)
				 && $entity->authorship instanceof Authorship
				 && !$entity->authorship->createdAt()) {
					$entity->authorship->create(
						$order->authorship->createdAt(),
						$order->authorship->createdBy()
					);
				}

				$this->_entityCreators[$name]->create($entity);
			}
		}

		// Fire the "create end" event before committing the transaction
		$event = new Event\TransactionalEvent($order);
		$event->setTransaction($this->_trans);
		$this->_eventDispatcher->dispatch(
			Events::CREATE_END,
			$event
		);

		$order = $event->getOrder();
		$trans = $event->getTransaction();

		$trans->commit();

		$event = new Event\Event($this->_loader->getByID($trans->getIDVariable('ORDER_ID')));
		$this->_eventDispatcher->dispatch(
			Events::CREATE_COMPLETE,
			$event
		);

		return $event->getOrder();
	}
}