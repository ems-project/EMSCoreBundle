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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $defaultOptions = [
            'required' => false,
            'firstLevelOnly' => true,
            'mapping' => $options['mapping'],
            'types' => ['text',  'keyword', 'string', 'integer'],
        ];

        $builder
            ->add(VersionFields::DATE_FROM, ContentTypeFieldPickerType::class, $defaultOptions)
            ->add(VersionFields::DATE_TO, ContentTypeFieldPickerType::class, $defaultOptions)
            ->add(VersionFields::VERSION_TAG, ContentTypeFieldPickerType::class, $defaultOptions)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['mapping']);
    }
}
