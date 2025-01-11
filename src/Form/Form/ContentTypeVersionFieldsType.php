<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\ContentType\Version\VersionFields;
use EMS\CoreBundle\Form\Field\ContentTypeFieldPickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeVersionFieldsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(VersionFields::DATE_FROM, ContentTypeFieldPickerType::class, [
                'required' => false,
                'firstLevelOnly' => true,
                'mapping' => $options['mapping'],
                'types' => ['date'],
            ])
            ->add(VersionFields::DATE_TO, ContentTypeFieldPickerType::class, [
                'required' => false,
                'firstLevelOnly' => true,
                'mapping' => $options['mapping'],
                'types' => ['date'],
            ])
            ->add(VersionFields::VERSION_TAG, ContentTypeFieldPickerType::class, [
                'required' => false,
                'firstLevelOnly' => true,
                'mapping' => $options['mapping'],
                'types' => ['keyword'],
            ])
        ;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['mapping']);
    }
}
