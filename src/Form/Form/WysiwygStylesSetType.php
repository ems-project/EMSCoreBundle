<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\FileType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WysiwygStylesSetType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', IconTextType::class, [
                'icon' => 'fa fa-tag',
                'label' => 'Styles set\'s name',
            ])
            ->add('formatTags', IconTextType::class, [
                'required' => false,
                'icon' => 'fa fa-header',
            ])
            ->add('contentCss', IconTextType::class, [
                'required' => false,
                'icon' => 'fa fa-css3',
            ])
            ->add('contentJs', IconTextType::class, [
                'required' => false,
                'icon' => 'fa fa-js',
            ])
            ->add('tableDefaultCss', IconTextType::class, [
                'label' => 'form.wysiwyg_style_set.table_default_css.title',
                'required' => false,
                'icon' => 'fa fa-table',
            ])
            ->add('saveDir', IconTextType::class, [
                'required' => false,
                'icon' => 'fa fa-folder',
            ])
            ->add('assets', FileType::class, [
                'required' => false,
                'meta_fields' => false,
            ])
            ->add('config', CodeEditorType::class, [
                'language' => 'ace/mode/json',
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
            ]);

        if (!$options['createform']) {
            $builder->add('remove', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-trash',
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'createform' => false,
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}
