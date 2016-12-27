<?php 

namespace EMS\CoreBundle\Form\Nature;

use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReorderType extends AbstractType
{

	/**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

    	$builder->add ( 'items', ItemsType::class, [
    			'result' => $options['result']
    	]);
    	
    	$builder->add ( 'reorder', SubmitEmsType::class, [
    			'attr' => [
    					'class' => 'btn-primary '
    			],
    			'icon' => 'fa fa-reorder'    			
    	]);
    }   

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
			'result' => [],
        ]);
    }
	
}