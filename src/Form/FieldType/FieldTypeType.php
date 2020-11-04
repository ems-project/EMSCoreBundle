<?php

namespace EMS\CoreBundle\Form\FieldType;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\CollectionItemFieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\SubfieldType;
use EMS\CoreBundle\Form\Field\FieldTypePickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Monolog\Logger;

class FieldTypeType extends AbstractType
{
    /** @var FieldTypePickerType $fieldTypePickerType */
    private $fieldTypePickerType;
    /** @var FormRegistryInterface $formRegistry */
    private $formRegistry;
    /** @var Logger $logger */
    private $logger;
    
    public function __construct(FieldTypePickerType $fieldTypePickerType, FormRegistryInterface $formRegistry, Logger $logger)
    {
        $this->fieldTypePickerType = $fieldTypePickerType;
        $this->formRegistry = $formRegistry;
        $this->logger = $logger;
    }


    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
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
                    'required' => false
            ]);
            $builder->add('ems:internal:add:field:name', TextType::class, [
                    'label' => 'Field\'s machine name',
                    'mapped' => false,
                    'required' => false,
            ]);

            $builder->add('add', SubmitEmsType::class, [
                    'attr' => [
                            'class' => 'btn-primary '
                    ],
                    'icon' => 'fa fa-plus'
            ]);
        } else if (strcmp(SubfieldType::class, $fieldType->getType()) != 0) {
            $builder->add('ems:internal:add:subfield:name', TextType::class, [
                    'label' => 'Subfield\'s name',
                    'mapped' => false,
                    'required' => false,
            ]);
            
            $builder->add('subfield', SubmitEmsType::class, [
                    'label' => 'Add',
                    'attr' => [
                            'class' => 'btn-primary '
                    ],
                    'icon' => 'fa fa-plus'
            ]);
            
            $builder->add('ems:internal:add:subfield:target_name', TextType::class, [
                    'label' => 'New field\'s machine name',
                    'mapped' => false,
                    'required' => false,
            ]);
            
            $builder->add('duplicate', SubmitEmsType::class, [
                    'label' => 'Duplicate',
                    'attr' => [
                            'class' => 'btn-primary '
                    ],
                    'icon' => 'fa fa-paste'
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
                            'class' => 'btn-danger btn-xs'
                    ],
                    'icon' => 'fa fa-trash'
            ]);
        }

        if ($fieldType !== null && null != $fieldType->getChildren() && $fieldType->getChildren()->count() > 0) {
            $childFound = false;
            /** @var FieldType $field */
            foreach ($fieldType->getChildren() as $idx => $field) {
                if (!$field->getDeleted() && ( $options['editSubfields'] || $field->getType() === SubfieldType::class)) {
                    $childFound = true;
                    $builder->add('ems_' . $field->getName(), FieldTypeType::class, [
                            'data' => $field,
                            'container' => true,
                            'editSubfields' => $options['editSubfields'],
                    ]);
                }
            }
            if ($childFound && $options['editSubfields']) {
                $builder->add('reorder', SubmitEmsType::class, [
                        'attr' => [
                                'class' => 'btn-primary '
                        ],
                        'icon' => 'fa fa-reorder'
                ]);
            }
        }
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'EMS\CoreBundle\Entity\FieldType',
            'container' => false,
            'path' => false,
            'new_field' => false,
            'editSubfields' => true,
        ));
    }


    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        /*get options for twig context*/
        parent::buildView($view, $form, $options);
        $view->vars ['editSubfields'] = $options ['editSubfields'];
    }
    
    public function dataFieldToArray(DataField $dataField)
    {
        $out = [];
        
        $this->logger->debug('dataFieldToArray for a type', [$dataField->getFieldType()->getType()]);
        
//         $dataFieldType = new CollectionItemFieldType();

        /** @var DataFieldType $dataFieldType */
        if (null != $dataField->getFieldType()) {
            $this->logger->debug('Instanciate the FieldType', [$dataField->getFieldType()->getType()]);
            $dataFieldType = $this->fieldTypePickerType->getDataFieldType($dataField->getFieldType()->getType());
        } else {
            $this->logger->debug('Field Type not found shoud be a collectionn item');
            $dataFieldType = $this->formRegistry->getType(CollectionItemFieldType::class)->getInnerType();
        }
         
        $this->logger->debug('build object array 2', [ get_class($dataFieldType) ]);
        
        $dataFieldType->buildObjectArray($dataField, $out);
        
        
        $this->logger->debug('Builded', [json_encode($out), ]);

        /** @var DataField $child */
        foreach ($dataField->getChildren() as $child) {
            $this->logger->debug('build object array for child', [$child->getFieldType()]);
        
            //its a Collection Item
            if ($child->getFieldType() == null) {
                $this->logger->debug('empty');
                $subOut = [];
                foreach ($child->getChildren() as $grandchild) {
                    $subOut = array_merge($subOut, $this->dataFieldToArray($grandchild));
                }
                $out[$dataFieldType->getJsonName($dataField->getFieldType())][] = $subOut;
            } else if (! $child->getFieldType()->getDeleted()) {
                $this->logger->debug('not deleted');
                if ($dataFieldType->isNested()) {
                    $out[$dataFieldType->getJsonName($dataField->getFieldType())] = array_merge($out[$dataFieldType->getJsonName($dataField->getFieldType())], $this->dataFieldToArray($child));
                } else {
                    $out = array_merge($out, $this->dataFieldToArray($child));
                }
            }
            
            $this->logger->debug('build array for child done', [$child->getFieldType()]);
        }
        return $out;
    }
    
    public function generateMapping(FieldType $fieldType, $withPipeline = false)
    {
         /** @var DataFieldType $dataFieldType */
        $dataFieldType = $this->formRegistry->getType($fieldType->getType())->getInnerType();
        $mapping = $dataFieldType->generateMapping($fieldType, $withPipeline);
        $jsonName = $dataFieldType->getJsonName($fieldType);

        if (!$dataFieldType::hasMappedChildren()) {
            return $mapping;
        }

        /** @var FieldType $child */
        foreach ($fieldType->getChildren() as $child) {
            if (! $child->getDeleted()) {
                if ($jsonName !== null) {
                    if (isset($mapping[$jsonName]["properties"])) {
                        if (isset($mapping[$jsonName]["properties"]["attachment"]["properties"]["content"])) {
                            $mapping[$jsonName]["properties"]["attachment"]["properties"]["content"] = array_merge_recursive($mapping[$jsonName]["properties"]["attachment"]["properties"]["content"], $this->generateMapping($child, $withPipeline));
                        } elseif (isset($mapping[$jsonName]["properties"]["_content"])) {
                            $mapping[$jsonName]["properties"]["_content"] = array_merge_recursive($mapping[$jsonName]["properties"]["_content"], $this->generateMapping($child, $withPipeline));
                        } elseif (isset($mapping[$jsonName]["properties"]["filename"])) {
                            $mapping[$jsonName]["properties"]["filename"] = array_merge_recursive($mapping[$jsonName]["properties"]["filename"], $this->generateMapping($child, $withPipeline));
                        } else {
                            $mapping[$jsonName]["properties"] = array_merge_recursive($mapping[$jsonName]["properties"], $this->generateMapping($child, $withPipeline));
                        }
                    } else {
                        $mapping[$jsonName] = array_merge_recursive($mapping[$jsonName], $this->generateMapping($child, $withPipeline));
                    }
                } else {
                    $mapping = array_merge_recursive($mapping, $this->generateMapping($child, $withPipeline));
                }
            }
        }
        return $mapping;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'fieldTypeType';
    }
}
