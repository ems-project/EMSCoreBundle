<?php

namespace Ems\CoreBundle\Form\Form;


use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;
use Ems\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class JobType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('command', TextType::class, [
            		'required' =>false
            ])
			->add ( 'launch', SubmitEmsType::class, [ 
					'attr' => [ 
							'class' => 'btn-primary btn-sm ' 
					],
					'icon' => 'fa fa-save' 
			] );
    }
    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Ems\CoreBundle\Entity\Job'
        ));
    }
}
