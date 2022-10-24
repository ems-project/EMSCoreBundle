<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\ContentType\ContentTypeFields;
use EMS\CoreBundle\Form\Field\ContentTypeFieldPickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeFieldsType extends AbstractType
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
            ->add(ContentTypeFields::LABEL, ContentTypeFieldPickerType::class, $defaultOptions)
            ->add(ContentTypeFields::CIRCLES, ContentTypeFieldPickerType::class, $defaultOptions)
            ->add(ContentTypeFields::COLOR, ContentTypeFieldPickerType::class, $defaultOptions)
            ->add(ContentTypeFields::BUSINESS_ID, ContentTypeFieldPickerType::class, $defaultOptions)
            ->add(ContentTypeFields::CATEGORY, ContentTypeFieldPickerType::class, $defaultOptions)
            ->add(ContentTypeFields::ASSET, ContentTypeFieldPickerType::class, $defaultOptions)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['mapping']);
    }
}
