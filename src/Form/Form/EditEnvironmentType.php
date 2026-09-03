<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\RolePickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class EditEnvironmentType extends AbstractType
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
                'label' => t('environment.property.name', [], 'emsco-core'),
                'help' => t('environment.edit.notice_rename', [], 'emsco-core'),
            ])
            ->add('label', IconTextType::class, [
                'required' => false,
                'icon' => 'fa fa-header',
                'label' => t('environment.property.label', [], 'emsco-core'),
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'environment.property.description',
            ])
            ->add('color', ColorPickerType::class, [
                'required' => false,
                'label' => t('environment.property.color', [], 'emsco-core'),
            ])
            ->add('baseUrl', TextType::class, [
                'required' => false,
                'label' => t('environment.property.base_url', [], 'emsco-core'),
            ])
            ->add('inDefaultSearch', CheckboxType::class, [
                'required' => false,
                'label' => t('environment.property.option.default_search', [], 'emsco-core'),
            ])
            ->add('updateReferrers', CheckboxType::class, [
                'required' => false,
                'label' => t('environment.property.option.update_referrers', [], 'emsco-core'),
            ])
            ->add('templatePublication', CodeEditorType::class, [
                'required' => false,
                'min-lines' => 10,
                'label' => t('environment.property.template_publication', [], 'emsco-core'),
            ])
            ->add('rolePublish', RolePickerType::class, [
                'label' => t('environment.property.rolePublish', [], 'emsco-core'),
                'translation_domain' => EMSCoreBundle::TRANS_CORE,
                'required' => false,
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => ['class' => 'btn btn-primary btn-sm ', 'data-testid' => 'btn-action-save'],
                'icon' => 'fa fa-save',
                'label' => t('environment.edit.save', [], 'emsco-core'),
            ]);

        if (\array_key_exists('type', $options) && $options['type']) {
            $builder->add('circles', ObjectPickerType::class, [
                'required' => false,
                'type' => $options['type'],
                'multiple' => true,
                'label' => t('field.circles', [], 'emsco-core'),
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type' => null,
        ]);
    }
}
