<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AlignIndexesType extends SelectPickerType
{
    /**
     * @var EnvironmentService
     */
    private $service;
    
    /**
     * @param EnvironmentService $service
     */
    public function __construct(EnvironmentService $service)
    {
        parent::__construct();
        
        $this->service = $service;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $environments = $this->service->getExternalEnvironments();
        $choices = [];
        
        foreach ($environments as $env) {
            /* @var $env Environment */
            $choices[$env->getName()] = $env;
        }
        
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
            'multiple' => false,
            'label' => 'Align environment with:',
            'placeholder' => 'select external environment',
            'choices' => array_keys($choices),
            'choice_attr' => function ($name) use ($choices) {
                $env = $choices[$name];
            
                return [
                    'data-indexes' => '["'.implode('", "',$env->getIndexes()) . '"]',
                    'data-content' => '<span class="text-'.$env->getColor().'"><i class="fa fa-code-fork"></i>&nbsp;&nbsp;'. $env->getName().'</span>'
                ];
            }
        ]);
    }
}