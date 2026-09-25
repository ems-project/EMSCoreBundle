<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\ContentType\ContentTypeFields;
use EMS\CoreBundle\Form\Field\ContentTypeFieldPickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class ContentTypeFieldsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $defaultOptions = [
            'required' => false,
            'firstLevelOnly' => true,
            'mapping' => $options['mapping'],
            'types' => ['text',  'keyword', 'string', 'integer'],
        ];

        $builder
            ->add(ContentTypeFields::DISPLAY, TextType::class, [
                'required' => false,
                'label' => t('field.display', [], 'emsco-core'),
            ])
            ->add(ContentTypeFields::LABEL, ContentTypeFieldPickerType::class, [...$defaultOptions, 'label' => t('field.label', [], 'emsco-core')])
            ->add(ContentTypeFields::COLOR, ContentTypeFieldPickerType::class, [...$defaultOptions, 'label' => t('field.color', [], 'emsco-core')])
            ->add(ContentTypeFields::SORT, ContentTypeFieldPickerType::class, [...$defaultOptions, 'label' => t('field.sort', [], 'emsco-core')])
            ->add(ContentTypeFields::TOOLTIP, ContentTypeFieldPickerType::class, [...$defaultOptions, 'label' => t('field.tooltip', [], 'emsco-core')])
            ->add(ContentTypeFields::CIRCLES, ContentTypeFieldPickerType::class, [...$defaultOptions, 'label' => t('field.circles', [], 'emsco-core')])
            ->add(ContentTypeFields::BUSINESS_ID, ContentTypeFieldPickerType::class, [...$defaultOptions, 'label' => t('field.business_id', [], 'emsco-core')])
            ->add(ContentTypeFields::CATEGORY, ContentTypeFieldPickerType::class, [...$defaultOptions, 'label' => t('field.category', [], 'emsco-core')])
            ->add(ContentTypeFields::ASSET, ContentTypeFieldPickerType::class, [
                ...$defaultOptions,
                'label' => t('field.asset_field', [], 'emsco-core'),
                'types' => ['nested'],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['mapping']);
    }
}
