<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\WysiwygStylesSet;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\FileType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class WysiwygStylesSetType extends AbstractType
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
            ->add('formatTags', IconTextType::class, [
                'required' => false,
                'icon' => 'fa fa-header',
                'label' => t('field.format_tags', [], 'emsco-core'),
            ])
            ->add('contentCss', IconTextType::class, [
                'required' => false,
                'icon' => 'fa fa-brands fa-css3',
                'label' => t('field.content_css', [], 'emsco-core'),
            ])
            ->add('contentJs', IconTextType::class, [
                'required' => false,
                'icon' => 'fa fa-brands fa-js',
                'label' => t('field.content_js', [], 'emsco-core'),
            ])
            ->add('tableDefaultCss', IconTextType::class, [
                'required' => false,
                'icon' => 'fa fa-table',
                'label' => t('field.table_default_css', [], 'emsco-core'),
            ])
            ->add('saveDir', IconTextType::class, [
                'required' => false,
                'icon' => 'fa fa-folder',
                'label' => t('field.save_dir', [], 'emsco-core'),
            ])
            ->add('assets', FileType::class, [
                'required' => false,
                'meta_fields' => false,
            ])
            ->add('config', CodeEditorType::class, [
                'language' => 'ace/mode/json',
                'label' => t('field.config', [], 'emsco-core'),
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                    'data-testid' => 'btn-action-save',
                ],
                'label' => t('action.save', [], 'emsco-core'),
                'icon' => 'fa fa-save',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WysiwygStylesSet::class,
        ]);
    }
}
