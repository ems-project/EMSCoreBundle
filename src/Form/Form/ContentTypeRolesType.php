<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Form\Field\RolePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class ContentTypeRolesType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(ContentTypeRoles::VIEW, RolePickerType::class, [
            'label' => t('field.role_view', [], 'emsco-core'),
        ]);

        if ($options['managed']) {
            $builder
                ->add(ContentTypeRoles::CREATE, RolePickerType::class, [
                    'label' => t('field.role_create', [], 'emsco-core'),
                ])
                ->add(ContentTypeRoles::EDIT, RolePickerType::class, [
                    'label' => t('field.role_edit', [], 'emsco-core'),
                ])
                ->add(ContentTypeRoles::PUBLISH, RolePickerType::class, [
                    'label' => t('field.role_publish', [], 'emsco-core'),
                ])
                ->add(ContentTypeRoles::DELETE, RolePickerType::class, [
                    'label' => t('field.role_delete', [], 'emsco-core'),
                ])
                ->add(ContentTypeRoles::TRASH, RolePickerType::class, [
                    'label' => t('field.role_trash', [], 'emsco-core'),
                ])
                ->add(ContentTypeRoles::ARCHIVE, RolePickerType::class, [
                    'label' => t('field.role_archive', [], 'emsco-core'),
                ])
                ->add(ContentTypeRoles::SHOW_LINK_CREATE, RolePickerType::class, [
                    'label' => t('field.role_show_link_create', [], 'emsco-core'),
                ])
            ;
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['managed']);
    }
}
