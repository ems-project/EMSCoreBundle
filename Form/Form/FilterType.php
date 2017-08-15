<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use EMS\CoreBundle\Form\Field\AnalyzerOptionsType;
use EMS\CoreBundle\Entity\Filter;

class FilterType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    	$builder
	    	->add('name', null, array('required' => true))
	    	->add('label', null, array('required' => true))
// 	    	->add('options', AnalyzerOptionsType::class)
            ->add ( 'save', SubmitEmsType::class, [
            		'label' => 'Save',
            		'attr' => [
            				'class' => 'btn btn-primary pull-right'
            		],
            		'icon' => 'fa fa-save',
            ] );
    }
    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Filter::class
        ]);
    }
}
