<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\Helpers\Standard\Text;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ColorPickerType extends Select2Type
{
    /** @var array<string, ?string> */
    private array $choices = [
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

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => $this->choices,
            'choice_translation_domain' => false,
            'attr' => [
                'data-live-search' => true,
            ],
            'choice_attr' => fn ($category, $key, $index) => [
                'data-content' => "<div class='text-".$category."'><i class='fa fa-square'></i>&nbsp;&nbsp;".Text::humanize($key).'</div>',
            ],
            'choice_value' => fn ($value) => $value,
        ]);
    }
}
