<?php

namespace EMS\CoreBundle\Form\FieldType;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\CollectionItemFieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\SubfieldType;
use EMS\CoreBundle\Form\Field\FieldTypePickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class FieldTypeType extends AbstractType
{
    public function __construct(private readonly FieldTypePickerType $fieldTypePickerType, private readonly FormRegistryInterface $formRegistry, private readonly LoggerInterface $logger)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<mixed>                $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['data'];

        $builder->add('name', HiddenType::class);

        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $this->formRegistry->getType($fieldType->getType())->getInnerType();
        $fieldType->filterDisplayOptions($dataFieldType);

        $dataFieldType->buildOptionsForm($builder, $options);

        if ($dataFieldType->isContainer()) {
            $builder->add('ems:internal:add:field:class', FieldTypePickerType::class, [
                'label' => 'Field\'s type',
                'mapped' => false,
                'required' => false,
            ]);
            $builder->add('ems:internal:add:field:name', TextType::class, [
                'label' => 'Field\'s machine name',
                'mapped' => false,
                'required' => false,
            ]);

            $builder->add('add', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary ',
                ],
                'icon' => 'fa fa-plus',
            ]);
        } elseif (0 != \strcmp(SubfieldType::class, $fieldType->getType())) {
            $builder->add('ems:internal:add:subfield:name', TextType::class, [
                'label' => 'Subfield\'s name',
                'mapped' => false,
                'required' => false,
            ]);

            $builder->add('subfield', SubmitEmsType::class, [
                'label' => 'Add',
                'attr' => [
                    'class' => 'btn btn-primary ',
                ],
                'icon' => 'fa fa-plus',
            ]);

            $builder->add('ems:internal:add:subfield:target_name', TextType::class, [
                'label' => 'New field\'s machine name',
                'mapped' => false,
                'required' => false,
            ]);

            $builder->add('duplicate', SubmitEmsType::class, [
                'label' => 'Duplicate',
                'attr' => [
                    'class' => 'btn btn-primary ',
                ],
                'icon' => 'fa fa-paste',
            ]);
        }
        if (!$options['editSubfields']) {
            $builder->add('name', TextType::class, [
                'label' => 'Field\'s name',
                //                'mapped' => false,
                //                'required' => false,
            ]);
        }
        if (null != $fieldType->getParent() && $options['editSubfields']) {
            $builder->add('remove', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-danger btn-xs',
                ],
                'icon' => 'fa fa-trash',
            ]);
        }

        if ($fieldType->getChildren()->count() > 0) {
            $childFound = false;
            /** @var FieldType $field */
            foreach ($fieldType->getChildren() as $idx => $field) {
                if (!$field->getDeleted() && ($options['editSubfields'] || SubfieldType::class === $field->getType())) {
                    $childFound = true;
                    $builder->add('ems_'.$field->getName(), FieldTypeType::class, [
                        'data' => $field,
                        'container' => true,
                        'mapped' => false,
                        'editSubfields' => $options['editSubfields'],
                    ]);
                }
            }
            if ($childFound && $options['editSubfields']) {
                $builder->add('reorder', SubmitEmsType::class, [
                    'attr' => [
                        'class' => 'btn btn-primary ',
                    ],
                    'icon' => 'fa fa-reorder',
                ]);
            }
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FieldType::class,
            'container' => false,
            'path' => false,
            'new_field' => false,
            'editSubfields' => true,
        ]);
    }

    /**
     * @param FormView<FormView>   $view
     * @param FormInterface<mixed> $form
     * @param array<mixed>         $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /* get options for twig context */
        parent::buildView($view, $form, $options);
        $view->vars['editSubfields'] = $options['editSubfields'];
    }

    /**
     * @return array<mixed>
     */
    public function dataFieldToArray(DataField $dataField): array
    {
        $out = [];

        $this->logger->debug('dataFieldToArray for a type', [$dataField->giveFieldType()->getType()]);

        if (null !== $fieldType = $dataField->getFieldType()) {
            $this->logger->debug('Instanciate the FieldType', [$fieldType->getType()]);
            $dataFieldType = $this->fieldTypePickerType->getDataFieldType($fieldType->getType());
        } else {
            $this->logger->debug('Field Type not found shoud be a collectionn item');
            $dataFieldType = $this->formRegistry->getType(CollectionItemFieldType::class)->getInnerType();
        }

        $this->logger->debug('build object array 2', [$dataFieldType::class]);

        if (!$dataFieldType instanceof DataFieldType) {
            throw new \Exception('Invalid dataFieldType');
        }

        $dataFieldType->buildObjectArray($dataField, $out);

        $this->logger->debug('Builded', [Json::encode($out)]);

        /** @var DataField $child */
        foreach ($dataField->getChildren() as $child) {
            $this->logger->debug('build object array for child', [$child->getFieldType()]);

            // its a Collection Item
            if (null == $child->getFieldType()) {
                $this->logger->debug('empty');
                $subOut = [];
                foreach ($child->getChildren() as $grandchild) {
                    $subOut = \array_merge($subOut, $this->dataFieldToArray($grandchild));
                }
                foreach ($dataFieldType->getJsonNames($dataField->giveFieldType()) as $jsonName) {
                    $out[$jsonName][] = $subOut;
                }
            } elseif (!$child->giveFieldType()->getDeleted()) {
                $this->logger->debug('not deleted');
                if ($dataFieldType->isNested()) {
                    foreach ($dataFieldType->getJsonNames($dataField->giveFieldType()) as $jsonName) {
                        $out[$jsonName] = \array_merge($out[$jsonName], $this->dataFieldToArray($child));
                    }
                } else {
                    $out = \array_merge($out, $this->dataFieldToArray($child));
                }
            }

            $this->logger->debug('build array for child done', [$child->getFieldType()]);
        }

        return $out;
    }

    /**
     * @return array<mixed>
     */
    public function generateMapping(FieldType $fieldType): array
    {
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $this->formRegistry->getType($fieldType->getType())->getInnerType();
        $mapping = $dataFieldType->generateMapping($fieldType);
        $jsonNames = $dataFieldType->getJsonNames($fieldType);

        if (!$dataFieldType::hasMappedChildren()) {
            return $mapping;
        }

        /** @var FieldType $child */
        foreach ($fieldType->getChildren() as $child) {
            if (!$child->getDeleted()) {
                if (\count($jsonNames) > 0) {
                    foreach ($jsonNames as $jsonName) {
                        if (isset($mapping[$jsonName]['properties'])) {
                            if (isset($mapping[$jsonName]['properties']['attachment']['properties']['content'])) {
                                $mapping[$jsonName]['properties']['attachment']['properties']['content'] = \array_merge_recursive($mapping[$jsonName]['properties']['attachment']['properties']['content'], $this->generateMapping($child));
                            } elseif (isset($mapping[$jsonName]['properties']['_content'])) {
                                $mapping[$jsonName]['properties']['_content'] = \array_merge_recursive($mapping[$jsonName]['properties']['_content'], $this->generateMapping($child));
                            } elseif (isset($mapping[$jsonName]['properties']['filename'])) {
                                $mapping[$jsonName]['properties']['filename'] = \array_merge_recursive($mapping[$jsonName]['properties']['filename'], $this->generateMapping($child));
                            } else {
                                $mapping[$jsonName]['properties'] = \array_merge_recursive($mapping[$jsonName]['properties'], $this->generateMapping($child));
                            }
                        } else {
                            $mapping[$jsonName] = \array_merge_recursive($mapping[$jsonName], $this->generateMapping($child));
                        }
                    }
                } else {
                    $mapping = \array_merge_recursive($mapping, $this->generateMapping($child));
                }
            }
        }

        return $mapping;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'fieldTypeType';
    }
}
