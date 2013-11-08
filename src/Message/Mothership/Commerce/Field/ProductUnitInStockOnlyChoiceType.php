<?php

namespace Message\Mothership\Commerce\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

class ProductUnitInStockOnlyChoiceType extends AbstractType
{
	protected $_container;

	public function setDefaultOptions(OptionsResolverInterface $resolver)
	{
		$resolver->setDefaults(array(
			'oos' => array(),
			'oos_label' => function($label) {
				return $label . ' (Out of stock)';
			}
		));
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildView(FormView $view, FormInterface $form, array $options)
	{
		$oosLabelClosure = $options['oos_label'];

		if ($oosLabelClosure instanceof \Closure) {
			foreach ($view->vars['choices'] as $choice) {
				if (!in_array($choice->value, $options['oos'])) {
					continue;
				}

				$choice->label = $oosLabelClosure($choice->label);
			}
		}

		$view->vars = array_replace($view->vars, array(
			'oos'       => $options['oos'],
			'oos_label' => $options['oos_label'],
		));
	}

	public function getParent()
	{
		return 'choice';
	}

	public function getName()
	{
		return 'ms_product_unit_in_stock';
	}
}

