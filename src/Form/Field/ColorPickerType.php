<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\OptionsResolver\OptionsResolver;

class ColorPickerType extends SelectPickerType
{
    private $choices = [
         'not-defined' => null,
         'red' => 'red',
         'maroon' => 'maroon',
        'fuchsia' => 'fuchsia',
         'orange' => 'orange',
         'yellow' => 'yellow',
         'olive' => 'olive',
         'green' => 'green',
         'lime' => 'lime',
         'teal' => 'teal',
         'aqua' => 'aqua',
         'light-blue' => 'light-blue',
         'blue' => 'blue',
         'purple' => 'purple',
         'navy' => 'navy',
         'black' => 'black',
         'grey' => 'grey',
    ];

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choices' => $this->choices,
            'attr' => [
                    'data-live-search' => true,
            ],
            'choice_attr' => function ($category, $key, $index) {
                return [
                        'data-content' => "<div class='text-".$category."'><i class='fa fa-square'></i>&nbsp;&nbsp;".$this->humanize($key).'</div>',
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
        ]);
    }
}
