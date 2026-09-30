<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Form\TranslationsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class DisplayOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('label', IconTextType::class, [
            'label' => t('field.label', [], 'emsco-core'),
            'required' => false,
            'icon' => 'fa fa-tag',
        ]);
        $builder->add('labelTranslations', TranslationsType::class, [
            'label' => t('field.label_translations', [], 'emsco-core'),
            'required' => false,
        ]);
        $builder->add('class', IconTextType::class, [
            'required' => false,
            'label' => t('field.bootstrap_class', [], 'emsco-core'),
            'icon' => 'fa fa-brands fa-css3',
        ]);
        $builder->add('lastOfRow', CheckboxType::class, [
            'required' => false,
            'label' => t('field.last_of_row', [], 'emsco-core'),
        ])->add('helptext', TextareaType::class, [
            'label' => t('field.helptext', [], 'emsco-core'),
            'required' => false,
        ])->add('helptextTranslations', TranslationsType::class, [
            'label' => t('field.helptext_translations', [], 'emsco-core'),
            'entry_label_type' => TextareaType::class,
            'required' => false,
        ]);
    }
}
