<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Form\Field\RolePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeRolesType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(ContentTypeRoles::VIEW, RolePickerType::class);

        if ($options['managed']) {
            $builder
                ->add(ContentTypeRoles::CREATE, RolePickerType::class)
                ->add(ContentTypeRoles::EDIT, RolePickerType::class)
                ->add(ContentTypeRoles::PUBLISH, RolePickerType::class)
                ->add(ContentTypeRoles::DELETE, RolePickerType::class)
                ->add(ContentTypeRoles::TRASH, RolePickerType::class)
                ->add(ContentTypeRoles::ARCHIVE, RolePickerType::class)
                ->add(ContentTypeRoles::SHOW_LINK_CREATE, RolePickerType::class)
            ;
        }

        $builder->add(ContentTypeRoles::SHOW_LINK_SEARCH, RolePickerType::class);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['managed']);
    }
}
