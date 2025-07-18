<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

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
                'label' => t('field.name', [], 'emsco-core'),
            ])
            ->add('editor', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    t('key.ckeditor4', [], 'emsco-core')->getMessage() => WysiwygProfile::CKEDITOR4,
                    t('key.ckeditor5', [], 'emsco-core')->getMessage() => WysiwygProfile::CKEDITOR5,
                ],
                'label' => t('field.editor', [], 'emsco-core'),
                'choice_translation_domain' => 'emsco-core',
            ])
            ->add('config', CodeEditorType::class, [
                'language' => 'ace/mode/json',
                'label' => t('field.config', [], 'emsco-core'),
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'label' => t('action.save', [], 'emsco-core'),
                'icon' => 'fa fa-save',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WysiwygProfile::class,
        ]);
    }
}
