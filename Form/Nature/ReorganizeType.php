<?php 

namespace EMS\CoreBundle\Form\Nature;

use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use EMS\CoreBundle\Form\DataField\TextareaFieldType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class ReorganizeType extends AbstractType
{

	/**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    	
    	$builder->add ( 'structure', HiddenType::class, [
    	])
    	->add ( 'reorder', SubmitEmsType::class, [
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
        ]);
    }
	
}