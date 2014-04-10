<?php

namespace Message\Mothership\Commerce;

use Message\Cog\Event\EventListener as BaseListener;
use Message\Cog\Event\SubscriberInterface;
use Message\Cog\HTTP\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Message\Mothership\Commerce\Order\Event;
use Message\Mothership\Commerce\Order\Events;
use Message\Mothership\ControlPanel\Event\BuildMenuEvent;
use Message\Mothership\ControlPanel\Event\Dashboard\DashboardIndexEvent;
use Message\Mothership\ControlPanel\Event\Dashboard\ActivitySummaryEvent;

/**
 * Event listener for core Mothership Commerce functionality.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class EventListener extends BaseListener implements SubscriberInterface
{
	/**
	 * {@inheritDoc}
	 */
	static public function getSubscribedEvents()
	{
		return array(
			BuildMenuEvent::BUILD_MAIN_MENU => array(
				array('registerMainMenuItems'),
			),
			Events::BUILD_ORDER_SIDEBAR => array(
				array('registerSidebarItems'),
			),
			Events::BUILD_ORDER_TABS => array(
				array('registerTabItems'),
			),
			DashboardIndexEvent::DASHBOARD_INDEX => array(
				'buildDashboardIndex'
			),
			ActivitySummaryEvent::DASHBOARD_ACTIVITY_SUMMARY => array(
				'buildDashboardUserSummary',
			),
		);
	}

	/**
	 * Register items to the main menu of the control panel.
	 *
	 * @param BuildMenuEvent $event The event
	 */
	public function registerMainMenuItems(BuildMenuEvent $event)
	{
		$event->addItem('ms.commerce.product.dashboard', 'Products', array('ms.product'));
		$event->addItem('ms.commerce.order.view.dashboard', 'Orders', array('ms.order'));
	}

	/**
	 * Register items to the sidebar of the orders-pages.
	 *
	 * @param BuildMenuEvent $event The event
	 */
	public function registerSidebarItems(BuildMenuEvent $event)
	{
		$event->addItem('ms.commerce.order.view.all', 'All Orders');
		$event->addItem('ms.commerce.order.view.shipped', 'Shipped Orders');
	}

	/**
	 * Register items to the sidebar of the orders-pages.
	 *
	 * @param BuildMenuEvent $event The event
	 */
	public function registerTabItems(Event\BuildOrderTabsEvent $event)
	{
		$event->addItem('ms.commerce.order.detail.view', 			'ms.commerce.order.order.overview-title');
		$event->addItem('ms.commerce.order.detail.view.items', 		'ms.commerce.order.item.listing-title');
		$event->addItem('ms.commerce.order.detail.view.addresses', 	'ms.commerce.order.address.listing-title');
		$event->addItem('ms.commerce.order.detail.view.payments', 	'ms.commerce.order.payment.listing-title');
		$event->addItem('ms.commerce.order.detail.view.dispatches', 'ms.commerce.order.dispatch.listing-title');
		$event->addItem('ms.commerce.order.detail.view.notes', 		'ms.commerce.order.note.listing-title');
		$event->addItem('ms.commerce.order.detail.view.documents', 	'ms.commerce.order.document.listing-title');
	}

	/**
	 * Add controller references to the dashboard index.
	 *
	 * @param  DashboardIndexEvent $event
	 */
	public function buildDashboardIndex(DashboardIndexEvent $event)
	{
		$event->addReference('Message:Mothership:Commerce::Controller:Module:Dashboard:PopularProducts#index');
		$event->addReference('Message:Mothership:Commerce::Controller:Module:Dashboard:OrdersActivity#index');
		$event->addReference('Message:Mothership:Commerce::Controller:Module:Dashboard:TotalSales#index');
		$event->addReference('Message:Mothership:Commerce::Controller:Module:Dashboard:DiscountRevenue#index');
	}

	/**
	 * Add the user's last edited product and order into the user summary
	 * dashboard block.
	 *
	 * @param  ActivitySummaryEvent $event
	 */
	public function buildDashboardUserSummary(ActivitySummaryEvent $event)
	{
		$productID = $this->get('db.query')->run("
			SELECT product_id
			FROM product
			WHERE :userID?b IS NULL OR updated_by = :userID?i
			ORDER BY updated_at DESC
			LIMIT 1
		", [
			'userID' => $event->getUser()->id
		]);

		if (count($productID)) {
			$product = $this->get('product.loader')->getByID($productID[0]->product_id);

			$event->addActivity([
				'label' => 'Last edited product',
				'date'  => $product->authorship->updatedAt(),
				'name'  => $product->name,
				'url'   => '',
			]);
		}

		$orderID = $this->get('db.query')->run("
			SELECT order_id
			FROM order_summary
			WHERE :userID?b IS NULL OR updated_by = :userID?i
			ORDER BY updated_at DESC
			LIMIT 1
		", [
			'userID' => $event->getUser()->id
		]);

		if (count($orderID)) {
			$order = $this->get('order.loader')->getByID($orderID[0]->order_id);

			$event->addActivity([
				'label' => 'Last edited order',
				'date'  => $order->authorship->updatedAt(),
				'name'  => '#' . $order->id,
				'url'   => '',
			]);
		}
	}
}