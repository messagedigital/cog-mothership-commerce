<?php

namespace Message\Mothership\Commerce\Order;

class Events
{

	const ASSEMBLER_UPDATE         = 'commerce.order.assembler.update';
	const ASSEMBLER_ADDRESS_UPDATE = 'commerce.order.assembler.address.update';
	const CREATE_VALIDATE          = 'commerce.order.create.validate';
	const CREATE_START             = 'commerce.order.create.start';
	const CREATE_ITEM              = 'commerce.order.create.item';
	const CREATE_END               = 'commerce.order.create.end';
	const CREATE_COMPLETE          = 'commerce.order.create.complete';
	const EDIT                     = 'commerce.order.edit';
	const SET_STATUS               = 'commerce.order.status';
	const ITEM_STATUS_CHANGE       = 'commerce.order.item.status.change';
	const DISPATCH_POSTAGE_AUTO    = 'commerce.order.dispatch.postage.automatically';
	const BUILD_ORDER_SIDEBAR      = 'commerce.order.sidebar.create';
	const BUILD_ORDER_TABS         = 'commerce.order.tabs.create';
	const CREATE_NOTE              = 'commerce.order.note.create';
}