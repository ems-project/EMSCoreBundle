<?php 

namespace Ems\CoreBundle\Form\DataField\Options;



use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;


/**
 * Some DataField need a sub suboption form in display
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class SubOptionsType extends AbstractType
{
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
	}
}