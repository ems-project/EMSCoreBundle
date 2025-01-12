<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * It's a compound field for field specific mapping option.
 * All options defined here are passed to
 * Elasticsearch as field mapping.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class MappingOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['field_type'])
            ->setAllowedTypes('field_type', FieldType::class)
        ;
    }
}
