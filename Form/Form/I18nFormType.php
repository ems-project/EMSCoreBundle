<?php

namespace Ems\CoreBundle\Form\Form;

use Ems\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
/**
 * use to filter in index page of i18n
 * 
 * @author im
 *
 */
class I18nFormType extends AbstractType
{
    /** 
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('identifier', null, array('required' => false, 'label' => 'Key'))
            ->add('filter', SubmitEmsType::class, [
            		'attr' => [
            				'class' => 'btn-primary btn-sm'
            		],
            		'icon' => 'fa fa-columns'
            ]);
    }
}
