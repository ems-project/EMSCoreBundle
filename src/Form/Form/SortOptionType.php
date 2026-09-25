<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class SortOptionType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('name', IconTextType::class, [
            'icon' => 'fa fa-tag',
            'label' => t('field.name', [], 'emsco-core'),
        ])
        ->add('field', TextType::class, [
            'label' => t('field.field', [], 'emsco-core'),
        ])
        ->add('inverted', CheckboxType::class, [
            'label' => t('field.inverted', [], 'emsco-core'),
            'required' => false,
        ])
        ->add('icon', IconPickerType::class, [
            'required' => false,
        ]);
    }
}
