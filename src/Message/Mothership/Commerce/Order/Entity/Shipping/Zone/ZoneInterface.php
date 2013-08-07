<?php

namespace Message\Mothership\Commerce\Order\Entity\Shipping;

/**
 * Interface defining a shipping zone.
 *
 */
interface ZoneInterface
{
	/**
	 * Get the name for the shipping zone used internally as an identifier.
	 *
	 * @return string
	 */
	public function getName();

	/**
	 * Get the name for the shipping zone that is suitable to be displayed to
	 * users.
	 *
	 * @return string
	 */
	public function getDisplayName();


}