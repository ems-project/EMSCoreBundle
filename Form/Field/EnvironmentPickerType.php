<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\OptionsResolver\OptionsResolver;
use EMS\CoreBundle\Entity\Environment;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class EnvironmentPickerType extends ChoiceType
{
    
    private $choices;
    private $service;
    
    public function __construct(EnvironmentService $service)
    {
        parent::__construct();
        $this->service = $service;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getBlockPrefix()
    {
        return 'selectpicker';
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
//         $this->choices = [];
        $keys = [];
        
        
        
//         /**@var Environment $choice*/
//         foreach ($this->service->getAllInMyCircle() as $choice){
//             $this->choices[$choice->getName()] = $choice;
//         }
        
        $environments = null;
        
        if ($options['inMyCircle']) {
            $environments = $this->service->getAllInMyCircle();
        } else {
            $environments = $this->service->getAll();
        }
        
        $this->service->getAllInMyCircle();
        
        /**@var Environment $choice*/
        foreach ($environments as $key => $choice) {
            if (($choice->getManaged() || !$options['managedOnly']) && ! in_array($choice->getName(), $options['ignore'])) {
                $keys[] = $choice->getName();
                $this->choices[$choice->getName()] = $choice;
            }
        }
        $options['choices'] = $keys;
        parent::buildForm($builder, $options);
    }
    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $this->choices = [];
        parent::configureOptions($resolver);
        
        $resolver->setDefaults(array(
            'choices' => [],
            'attr' => [
                    'data-live-search' => false
            ],
            'choice_attr' => function ($category, $key, $index) {
                /** @var \EMS\CoreBundle\Form\DataField\DataFieldType $dataFieldType */
                $dataFieldType = $this->choices[$index];
                return [
                        'data-content' => '<span class="text-'.$dataFieldType->getColor().'"><i class="fa fa-square"></i>&nbsp;&nbsp;'.$dataFieldType->getName().'</span>'
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
            'multiple' => false,
            'managedOnly' => true,
            'inMyCircle' => true,
            'ignore' => [],
            'choice_translation_domain' => false,
        ));
    }
}
