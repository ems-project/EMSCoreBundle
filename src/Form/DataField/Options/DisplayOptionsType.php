<?php

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Form\Field\IconTextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * It's a coumpound field for field specific display option.
 * All options defined here are passed to
 * correspond eMS data compound field.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class DisplayOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('label', IconTextType::class, [
            'required' => false,
            'icon' => 'fa fa-tag',
        ]);
        $builder->add('class', IconTextType::class, [
            'required' => false,
            'label' => 'Bootstrap class',
            'icon' => 'fa fa-css3',
        ]);
        $builder->add('lastOfRow', CheckboxType::class, [
            'required' => false,
            'label' => 'Last item of the row',
        ])->add('helptext', TextareaType::class, [
            'required' => false,
        ]);
    }
}
