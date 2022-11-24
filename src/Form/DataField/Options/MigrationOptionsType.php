<?php

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformers;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * It's a coumpound field for field specific migration option.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class MigrationOptionsType extends AbstractType
{
    private ContentTransformers $transformers;

    public function __construct(ContentTransformers $transformers)
    {
        $this->transformers = $transformers;
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('protected', CheckboxType::class, ['required' => false]);

        /** @var FieldType $fieldType */
        $fieldType = $options['field_type'];
        $transformers = $this->transformers->getMigrationOptionsChoices($fieldType->getType());

        if (\count($transformers) > 0) {
            $builder->add('transformers', CollectionType::class, [
                'entry_type' => MigrationOptionsTransformerType::class,
                'entry_options' => [
                    'transformers' => \array_merge(['Select a transformer' => ''], $transformers),
                ],
                'label' => false,
                'attr' => [
                    'class' => 'a2lix_lib_sf_collection',
                    'data-lang-add' => 'Add transformer',
                    'data-lang-remove' => 'Delete transformer',
                    'data-entry-remove-class' => 'btn btn-sm btn-default',
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'block_prefix' => 'tags',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['field_type'])
            ->setAllowedTypes('field_type', FieldType::class)
        ;
    }
}
