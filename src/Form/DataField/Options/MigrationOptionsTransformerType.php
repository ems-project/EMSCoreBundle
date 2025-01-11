<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField\Options;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MigrationOptionsTransformerType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('class', ChoiceType::class, [
            'choices' => $options['transformers'],
        ]);
        $builder->add('config', TextType::class, ['required' => false]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'data_field_migration_transformer';
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['transformers']);
    }
}
