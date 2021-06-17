<?php

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * It's the default option compound field of eMS data type.
 * The panes for display and mapping options are added.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class OptionsType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['field_type'])
            ->setAllowedTypes('field_type', FieldType::class)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'data_field_options';
    }

    public function hasMappingOptions()
    {
        return false;
    }

    public function hasMigrationOptions()
    {
        return true;
    }

    public function hasExtraOptions()
    {
        return true;
    }

    public function generateMapping(array $options, FieldType $current)
    {
        return [];
    }
}
