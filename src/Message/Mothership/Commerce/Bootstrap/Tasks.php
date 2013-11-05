<?php

namespace Message\Mothership\Commerce\Bootstrap;

use Message\Cog\Bootstrap\TasksInterface;
use Message\Mothership\Commerce\Task;
use Message\Mothership\Commerce\Forex;
use Message\Mothership\Commerce\Product;

class Tasks implements TasksInterface
{
    public function registerTasks($tasks)
    {
        // Order related ports
        $tasks->add(new Task\Porting\Order\OrderSummary('commerce:porting:order:summary'), 'Ports order_summary from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderShipping('commerce:porting:order:shipping'), 'Ports order_shipping from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderAddress('commerce:porting:order:address'), 'Ports order_address from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderItem('commerce:porting:order:item'), 'Ports order_item from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderRefund('commerce:porting:order:refund'), 'Ports order_refund from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderPayment('commerce:porting:order:payment'), 'Ports order_payment from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderNote('commerce:porting:order:note'), 'Ports order_note from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderMetadata('commerce:porting:order:metadata'), 'Ports order_note from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderItemStatus('commerce:porting:order:item_status'), 'Ports order_item_status from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderItemReturn('commerce:porting:order:item_return'), 'Ports order_item_return from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderItemPersonalisation('commerce:porting:order:item_personalisation'), 'Ports order_item_personalisation from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderItemDispatch('commerce:porting:order:item_dispatch'), 'Ports order_item_dispatch from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderDispatch('commerce:porting:order:dispatch'), 'Ports order_dispatch from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderDiscount('commerce:porting:order:discount'), 'Ports order_discount from pre mothership');
        $tasks->add(new Task\Porting\Order\OrderDispatchFiles('commerce:porting:order:dispatch_files'), 'Ports dispatch files to the file syetem from pre mothership');

        // Product related ports
        $tasks->add(new Task\Porting\Product\Product('commerce:porting:product:product'), 'Ports catalogue and catalogue_info from pre mothership');
        $tasks->add(new Task\Porting\Product\ProductUnit('commerce:porting:product:unit'), 'Ports product_unit from catalogue_unit from pre mothership');

        $tasks->add(new Task\Porting\User('commerce:porting:user'), 'Ports users and user addresses from pre mothership');

        $tasks->add(new Forex\FetchDataTask('commerce:forex:fetch'), 'Fetches the lastest forex data from the feed');

        $tasks->add(new Product\Stock\StockSnapshot('commerce:stock:snapshot'), 'Creates a snapshot of current stock levels');
    }
}