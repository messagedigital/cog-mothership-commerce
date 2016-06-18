<?php

namespace Message\Mothership\Commerce\Product\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Message\Mothership\Commerce\Product\Product;

class ProductPricing extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$product   = $options['product'];
		if(!$product instanceof Product) {
			throw new \InvalidArgumentException('Option `product` must be instance of Product');
		}

		$builder->add('prices', 'price_form', [
			'priced_entity' => $product,
		]);

		$builder->add('export_value', 'money', [
			'data'     => $product->exportValue,
			'currency' => $options['currency'],
		]);
	}

	/**
	 * Sets the default currency and tax rate options, product is required
	 *
	 * {@inheritDoc}
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
	{
		$resolver->setRequired([
			'product',
		]);

		$resolver->setOptional([
			'locale',
			'export-currency',
		]);

		$resolver->setDefaults([
			'locale'   => null,
			'currency' => 'GBP',
		]);
	}

	public function getName()
	{
		return 'product_pricing';
	}
}