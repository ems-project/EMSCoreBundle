<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformers;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class MigrationOptionsType extends AbstractType
{
    public function __construct(private readonly ContentTransformers $transformers)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('protected', CheckboxType::class, [
            'label' => t('field.protected', [], 'emsco-core'),
            'required' => false,
        ]);

        /** @var FieldType $fieldType */
        $fieldType = $options['field_type'];
        $transformers = $this->transformers->getMigrationOptionsChoices($fieldType->getType());

        if ([] !== $transformers) {
            $builder->add('transformers', CollectionType::class, [
                'label' => false,
                'translation_domain' => 'emsco-core',
                'entry_type' => MigrationOptionsTransformerType::class,
                'entry_options' => [
                    'transformers' => [...[t('field.transformers', [], 'emsco-core')->getMessage() => ''], ...$transformers],
                ],
                'attr' => [
                    'class' => 'a2lix_lib_sf_collection',
                    'data-lang-add' => t('action.add_type', ['type' => 'transformer'], 'emsco-core'),
                    'data-lang-remove' => t('action.remove_type', ['type' => 'transformer'], 'emsco-core'),
                    'data-entry-remove-class' => 'btn btn-sm btn-default',
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'block_prefix' => 'tags',
            ]);
        }
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
