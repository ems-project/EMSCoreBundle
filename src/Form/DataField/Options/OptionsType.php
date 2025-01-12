<?php

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class OptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['field_type'];

        $builder->add('displayOptions', DisplayOptionsType::class);

        if (!$fieldType->isJsonMenuNestedEditorField() || $fieldType->isJsonMenuNestedEditor()) {
            $builder->add('mappingOptions', MappingOptionsType::class, $options);
        }

        $builder->add('restrictionOptions', RestrictionOptionsType::class, $options);
        $builder->add('migrationOptions', MigrationOptionsType::class, $options);
        $builder->add('extraOptions', ExtraOptionsType::class);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['field_type'])
            ->setAllowedTypes('field_type', FieldType::class)
        ;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'data_field_options';
    }

    public function hasMappingOptions(): bool
    {
        return false;
    }

    public function hasMigrationOptions(): bool
    {
        return true;
    }

    public function hasExtraOptions(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    public function generateMapping(array $options, FieldType $current): array
    {
        return [];
    }
}
