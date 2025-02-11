<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ColorPickerType extends Select2Type
{
    /** @var array<string, ?string> */
    private array $choices = [
        'color.red' => 'red',
        'color.maroon' => 'maroon',
        'color.fuchsia' => 'fuchsia',
        'color.orange' => 'orange',
        'color.yellow' => 'yellow',
        'color.olive' => 'olive',
        'color.green' => 'green',
        'color.lime' => 'lime',
        'color.teal' => 'teal',
        'color.aqua' => 'aqua',
        'color.light-blue' => 'light-blue',
        'color.blue' => 'blue',
        'color.purple' => 'purple',
        'color.navy' => 'navy',
        'color.black' => 'black',
        'color.gray' => 'gray',
    ];

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => $this->choices,
            'required' => false,
            'choice_translation_domain' => EMSCoreBundle::TRANS_CORE,
            'attr' => [
                'data-live-search' => true,
            ],
            'choice_attr' => fn ($key) => [
                'data-icon' => \sprintf('fa fa-square text-%s', $key ?? 'muted'),
            ],
        ]);
    }
}
