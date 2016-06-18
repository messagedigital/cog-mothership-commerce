<?php

namespace Message\Mothership\Commerce\Form\Extension\Type;

use Symfony\Component\Form;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\FormBuilderInterface;

class PriceGroup extends Form\AbstractType
{
	protected $_priceTypes;

	public function __construct(array $priceTypes)
	{
		$this->_priceTypes = $priceTypes;
	}

	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$priceTypes = $options['price_types'];
		$currency   = $options['currency'];

		foreach ($priceTypes as $type => $value) {
			if (!empty($options['data']) && !empty($options['data'][$type])) {
				$data = $options['data'][$type];
			} else {
				$data = isset($options['pricing'][$type]) ? $options['pricing'][$type] : $options['default'];
			}

			$value = $value ?: 0;

			$fieldOpts = [
				'currency' => $currency,
				'label'    => "ms.commerce.product.pricing.$type.label",
				'data'     => $data,
				'attr'     => [
					'class' => 'price-field',
				]
			];

			if(isset($options['constraints'])) {
				$fieldOpts['constraints'] = $options['constraints'];
			}

			$builder->add($type, 'money', $fieldOpts);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
	{
		$resolver->setOptional([
			'pricing',
		]);

		$resolver->setRequired([
			'currency',
		]);

		$resolver->setDefaults([
			'price_types' => array_combine($this->_priceTypes, $this->_priceTypes),
			'default'     => null,
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName()
	{
		return 'price_group';
	}
}