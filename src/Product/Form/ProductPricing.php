<?php

namespace Message\Mothership\Commerce\Product\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Message\Mothership\Commerce\Product\Product;

class ProductPricing extends AbstractType
{
	protected $_taxRates;

	public function __construct(array $taxRates)
	{
		$this->_taxRates   = $taxRates;
	}

	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$product   = $options['product'];
		if(!$product instanceof Product) {
			throw new \InvalidArgumentException('Option `product` must be instance of Product');
		}

		$builder->add('prices', 'price_form', [
			'priced_entity' => $product,
		]);

		$builder->add('tax_rate', 'choice', [
			'choices' => $options['tax_rate'],
			'data'    => $product->taxRate,
		]);

		$builder->add('export_value', 'money', [
			'data' => $product->exportValue,
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
			'tax_rate',
		]);

		$resolver->setOptional([
			'locale',
		]);

		$resolver->setDefaults([
			'tax_rate'   => $this->_taxRates,
			'locale'     => null,
		]);
	}

	public function getName()
	{
		return 'product_pricing';
	}
}