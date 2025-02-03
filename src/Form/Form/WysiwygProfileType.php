<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class WysiwygProfileType extends AbstractType
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
                'label' => 'wysiwyg_profile.name.label',
                'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
            ])
            ->add('editor', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    'wysiwyg_profile.editor.ckeditor4' => WysiwygProfile::CKEDITOR4,
                    'wysiwyg_profile.editor.ckeditor5' => WysiwygProfile::CKEDITOR5,
                ],
                'label' => 'wysiwyg_profile.editor.label',
                'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
            ])
            ->add('config', CodeEditorType::class, [
                'language' => 'ace/mode/json',
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
                'label' => 'wysiwyg_profile.save.label',
                'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
            ]);

        if (!$options['createform']) {
            $builder->add('remove', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-trash',
                'label' => 'wysiwyg_profile.remove.label',
                'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
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
