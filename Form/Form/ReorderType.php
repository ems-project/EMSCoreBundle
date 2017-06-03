<?php 

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

class ReorderType extends AbstractType
{

	/**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

    	$builder->add ( 'items', HiddenType::class, [
    	]);
    	
    	$builder->add ( 'reorder', SubmitEmsType::class, [
    			'attr' => [
    					'class' => 'btn-primary '
    			],
    			'icon' => 'fa fa-reorder'    			
    	]);
    }   
	
}