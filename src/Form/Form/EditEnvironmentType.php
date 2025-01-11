<?php

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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EditEnvironmentType extends AbstractType
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
                'label' => 'environment.property.name',
                'help' => 'environment.edit.notice_rename',
            ])
            ->add('label', IconTextType::class, [
                'required' => false,
                'icon' => 'fa fa-header',
                'label' => 'environment.property.label',
            ])
            ->add('color', ColorPickerType::class, [
                'required' => false,
                'label' => 'environment.property.color',
            ])
            ->add('baseUrl', TextType::class, [
                'required' => false,
                'label' => 'environment.property.base_url',
            ])
            ->add('inDefaultSearch', CheckboxType::class, [
                'required' => false,
                'label' => 'environment.property.option.default_search',
            ])
            ->add('updateReferrers', CheckboxType::class, [
                'required' => false,
                'label' => 'environment.property.option.update_referrers',
            ])
            ->add('templatePublication', CodeEditorType::class, [
                'required' => false,
                'min-lines' => 10,
                'label' => 'environment.property.template_publication',
            ])
            ->add('rolePublish', RolePickerType::class, [
                'label' => 'environment.property.rolePublish',
                'translation_domain' => EMSCoreBundle::TRANS_ENVIRONMENT_DOMAIN,
                'required' => false,
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => ['class' => 'btn btn-primary btn-sm '],
                'icon' => 'fa fa-save',
                'label' => 'environment.edit.save',
            ]);

        if (\array_key_exists('type', $options) && $options['type']) {
            $builder->add('circles', ObjectPickerType::class, [
                'required' => false,
                'type' => $options['type'],
                'multiple' => true,
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type' => null,
            'translation_domain' => EMSCoreBundle::TRANS_ENVIRONMENT_DOMAIN,
        ]);
    }
}
