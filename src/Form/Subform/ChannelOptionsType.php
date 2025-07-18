<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Subform;

use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class ChannelOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('entryPath', null, [
                'label' => t('field.entry_path', [], 'emsco-core'),
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-8',
                ],
            ])
            ->add('attributes', CodeEditorType::class, [
                'label' => t('field.attributes', [], 'emsco-core'),
                'required' => true,
                'language' => 'ace/mode/json',
                'row_attr' => [
                    'class' => 'col-md-8',
                ],
            ])
            ->add('searchConfig', CodeEditorType::class, [
                'label' => t('field.search_config', [], 'emsco-core'),
                'required' => true,
                'language' => 'ace/mode/json',
                'row_attr' => [
                    'class' => 'col-md-8',
                ],
            ]);
    }
}
