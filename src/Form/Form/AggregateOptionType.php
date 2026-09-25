<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class AggregateOptionType extends AbstractType
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
        ->add('icon', IconPickerType::class, [
            'required' => false,
        ])
        ->add('config', CodeEditorType::class, [
            'label' => t('field.config', [], 'emsco-core'),
            'language' => 'ace/mode/json',
        ])
        ->add('template', CodeEditorType::class, [
            'label' => t('field.template', [], 'emsco-core'),
            'language' => 'ace/mode/twig',
        ]);
    }
}
