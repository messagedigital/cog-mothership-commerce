<?php

namespace Message\Mothership\Commerce\Form\Extension\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Message\Mothership\Commerce\Product\Price\PricedInterface;

class PriceForm extends AbstractType
{
	protected $_currencies;

	public function __construct(array $currencies)
	{
		$this->_currencies = $currencies;
	}

	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$entity   = $options['priced_entity'];
		if ($entity !== null && !$entity instanceof PricedInterface) {
			throw new \InvalidArgumentException('Option `priced_entity` must be instance of PricedInterface');
		}

		$priceData = [];
		$currencyData = !empty($options['data']) && !empty($options['data']['currencies']) ? $options['data']['currencies'] : [];

		$currencyCollection = $builder->create('currencies', 'form', ['label' => false,]);

		foreach ($options['currencies'] as $currency) {
			if ($entity) {	
				foreach($entity->getPrices() as $type => $price) {
					$priceData[$type] = ($price && $entity->hasPrice($type, $currency)) 
						? $price->getPrice($currency, $options['locale']) 
						: $options['default']
					;
				}
			}

			$currCollOptions = [
				'currency' => $currency,
				'label'    => $currency,
				'pricing'  => $entity ? $priceData : null,
			];

			if (!empty($currencyData[$currency])) {
				$currCollOptions['data'] = $currencyData[$currency];
			}

			if (isset($options['constraints'])) {
				$currCollOptions['constraints'] = $options['constraints'];
			}

			$currencyCollection->add($currency, 'price_group', $currCollOptions);
		}

		$builder->add($currencyCollection);
	}

	/**
	 * Sets the default currency optins, product is required
	 *
	 * {@inheritDoc}
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
	{
		$resolver->setRequired([
			'priced_entity',
		]);

		$resolver->setOptional([
			'locale',
		]);

		$resolver->setDefaults([
			'currencies' => $this->_currencies,
			'locale'     => null,
			'priced_entity' => null,
			'default'    => null,
		]);
	}

	public function getName()
	{
		return 'price_form';
	}
}