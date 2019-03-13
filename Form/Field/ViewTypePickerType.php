<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Form\View\ViewType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ViewTypePickerType extends SelectPickerType
{

    private $viewTypes;
    
    public function __construct()
    {
        parent::__construct();
        $this->viewTypes = array();
    }
    
    public function addViewType($viewType, $viewTypeId)
    {
        $this->viewTypes[$viewTypeId] = $viewType;
    }
    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'choices' => array_keys($this->viewTypes),
            'attr' => [
                    'data-live-search' => true
            ],
            'choice_attr' => function ($category, $key, $index) {
                /** @var ViewType $viewType */
                $viewType = $this->viewTypes[$index];
                return [
                        'data-content' => "<div class='text-".$category."'><i class='fa fa-square'></i>&nbsp;&nbsp;".$viewType->getLabel().'</div>'
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
        ));
    }
}
