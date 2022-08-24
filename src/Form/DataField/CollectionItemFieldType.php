<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Defined a Nested obecjt.
 * It's used to  groups subfields together.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class CollectionItemFieldType extends DataFieldType
{
    /**
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return 'Collection item object (this message should neve seen anywhere)';
    }

    public static function getIcon(): string
    {
        return 'fa fa-question';
    }

    public function getBlockPrefix(): string
    {
        return 'collectionitemtype';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /* get the metadata associate */
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $itemFieldType = new FieldType();
        $itemFieldType->setParent($fieldType);
        $itemFieldType->setType(CollectionItemFieldType::class);

        $builder->addViewTransformer(new DataFieldViewTransformer($itemFieldType, $this->formRegistry))
            ->addModelTransformer(new DataFieldModelTransformer($itemFieldType, $this->formRegistry));

        $builder->add('_ems_internal_deleted', HiddenType::class, [
            'required' => false,
            'attr' => [
                'class' => '_ems_internal_deleted',
            ],
        ]);

        /** @var FieldType $fieldType */
        foreach ($fieldType->getChildren() as $fieldType) {
            if (!$fieldType->getDeleted()) {
                /* merge the default options with the ones specified by the user */
                $options = \array_merge([
                        'metadata' => $fieldType,
                        'label' => false,
                        'migration' => $options['migration'],
                        'with_warning' => $options['with_warning'],
                        'raw_data' => $options['raw_data'],
                        'disabled_fields' => $options['disabled_fields'],
                        'referrer-ems-id' => $options['referrer-ems-id'],
                ], $fieldType->getDisplayOptions());
                $builder->add($fieldType->getName(), $fieldType->getType(), $options);
                $builder->get($fieldType->getName())
                    ->addViewTransformer(new DataFieldViewTransformer($fieldType, $this->formRegistry))
                    ->addModelTransformer(new DataFieldModelTransformer($fieldType, $this->formRegistry));
            }
        }

        $builder->add('remove_collection_item', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn btn-danger btn-sm remove-content-button',
                ],
                'label' => 'Remove',
                'icon' => 'fa fa-trash',
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (null == $data->getFieldType()) {
            $tmp = [];
            /** @var DataField $child */
            foreach ($data->getChildren() as $child) {
//                 $className = $child->getFieldType()->getType();
//                 $class = new $className;
                $class = $this->formRegistry->getType($child->giveFieldType()->getType());
                if (\method_exists($class, 'buildObjectArray')) {
                    $class->buildObjectArray($child, $tmp);
                }
            }
            $out[] = $tmp;
        } elseif (!$data->giveFieldType()->getDeleted()) {
            $out[$data->giveFieldType()->getName()] = [];
        }
    }

    public static function isNested(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public static function isContainer(): bool
    {
        /* this kind of compound field may contain children */
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function generateMapping(FieldType $current): array
    {
        return [
            $current->getName() => [
                'type' => 'nested',
                'properties' => [],
            ], ];
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        // Just an info to say to the parent collection that this rec has been updated by the submit
        $data['_ems_item_reverseViewTransform'] = true;
        $out = parent::reverseViewTransform($data, $fieldType);

        return $out;
    }
}
