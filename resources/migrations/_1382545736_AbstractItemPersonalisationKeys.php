<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1382545736_AbstractItemPersonalisationKeys extends Migration
{
	public function up()
	{
		$this->run("ALTER TABLE `order_item_personalisation` DROP `sender_name`;");
		$this->run("ALTER TABLE `order_item_personalisation` DROP `recipient_name`;");
		$this->run("ALTER TABLE `order_item_personalisation` DROP `recipient_email`;");
		$this->run("ALTER TABLE `order_item_personalisation` DROP `message`;");
		$this->run("ALTER TABLE `order_item_personalisation` ADD `name` VARCHAR(255)  NOT NULL  DEFAULT ''  AFTER `item_id`;");
		$this->run("ALTER TABLE `order_item_personalisation` ADD `value` TEXT  NULL  AFTER `name`;");
		$this->run("ALTER TABLE `order_item_personalisation` DROP PRIMARY KEY;");
		$this->run("ALTER TABLE `order_item_personalisation` ADD PRIMARY KEY (`item_id`, `name`);");
	}

	public function down()
	{
		$this->run("ALTER TABLE `order_item_personalisation` DROP `name`;");
		$this->run("ALTER TABLE `order_item_personalisation` DROP `value`;");
		$this->run("ALTER TABLE `order_item_personalisation` DROP PRIMARY KEY;");
		$this->run("ALTER TABLE `order_item_personalisation` ADD PRIMARY KEY (`item_id`);");
		$this->run("ALTER TABLE `order_item_personalisation` ADD `sender_name` VARCHAR(255)  NULL  DEFAULT NULL  AFTER `item_id`;");
		$this->run("ALTER TABLE `order_item_personalisation` ADD `recipient_name` VARCHAR(255)  NULL  DEFAULT NULL  AFTER `sender_name`;");
		$this->run("ALTER TABLE `order_item_personalisation` ADD `recipient_email` VARCHAR(255)  NULL  DEFAULT NULL  AFTER `recipient_name`;");
		$this->run("ALTER TABLE `order_item_personalisation` ADD `message` TEXT  NULL  AFTER `recipient_email`;");
	}
}