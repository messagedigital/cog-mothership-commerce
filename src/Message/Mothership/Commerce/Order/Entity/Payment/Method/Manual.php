<?php

namespace Message\Mothership\Commerce\Order\Entity\Payment\Method;

use Message\Mothership\Commerce\Order\Entity\Payment\MethodInterface;

/**
 * Manual payment method.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Manual implements MethodInterface
{
	/**
	 * {@inheritdoc}
	 */
	public function getName()
	{
		return 'manual';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDisplayName()
	{
		return 'Manual';
	}
}