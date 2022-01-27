<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Form\EmsCollectionType;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\ElasticsearchService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class CollectionFieldType extends DataFieldType
{
    /** @var DataService */
    private $dataService;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, DataService $dataService, LoggerInterface $logger)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
        $this->dataService = $dataService;
        $this->logger = $logger;
    }

    public function getLabel()
    {
        return 'Collection (manage array of children types)';
    }

    public static function getIcon()
    {
        return 'fa fa-plus fa-rotate';
    }

    public function importData(DataField $dataField, $sourceArray, $isMigration)
    {
        $migrationOptions = $dataField->getFieldType()->getMigrationOptions();
        if (!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
            if (!\is_array($sourceArray)) {
                $sourceArray = [$sourceArray];
            }

            $dataField->getChildren()->clear();
            foreach ($sourceArray as $idx => $item) {
                $colItem = new DataField();
                $colItem->setOrderKey($idx);
                $colItem->setFieldType(null); // it's a collection item
                foreach ($dataField->getFieldType()->getChildren() as $grandChildKey => $childFieldType) {
                    /** @var FieldType $childFieldType */
                    if (!$childFieldType->getDeleted()) {
                        $grandChild = new DataField();
                        $grandChild->setOrderKey(0);
                        $grandChild->setParent($colItem);
                        $grandChild->setFieldType($childFieldType);
                        $this->dataService->updateDataStructure($childFieldType, $grandChild);
                        if (\is_array($item)) {
                            $this->dataService->updateDataValue($grandChild, $item, $isMigration);
                        } else {
                            $this->logger->warning('form.data_field.collection.import_not_an_array', [
                                'import_data' => $item,
                                'field_name' => $dataField->getFieldType()->getName(),
                            ]);
                        }

                        $colItem->addChild($grandChild, $grandChildKey);
                    }
                }

                $dataField->addChild($colItem, $idx);
                $colItem->setParent($dataField);
            }
        }

        return [$dataField->getFieldType()->getName()];
    }

    public function getParent()
    {
        return EmsCollectionType::class;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        /* give options for twig context */
        parent::buildView($view, $form, $options);
        $view->vars['icon'] = $options['icon'];
        $view->vars['singularLabel'] = $options['singularLabel'];
        $view->vars['itemBootstrapClass'] = $options['itemBootstrapClass'];
        $view->vars['sortable'] = $options['sortable'];
        $view->vars['collapsible'] = $options['collapsible'];
        $view->vars['labelField'] = $options['labelField'];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        /* an optional icon can't be specified ritgh to the container label */
        $resolver->setDefault('icon', null);
        $resolver->setDefault('singularLabel', null);
        $resolver->setDefault('collapsible', false);
        $resolver->setDefault('sortable', false);
        $resolver->setDefault('itemBootstrapClass', null);
        $resolver->setDefault('labelField', null);
    }

    public static function isContainer()
    {
        /* this kind of compound field may contain children */
        return true;
    }

    public static function isCollection()
    {
        return true;
    }

    public function isValid(DataField &$dataField, DataField $parent = null, &$masterRawData = null)
    {
        if ($this->hasDeletedParent($parent)) {
            return true;
        }

        $isValid = true;
        //Madatory Validation
        //$isValid = $isValid && $this->isMandatory($dataField);

        $restrictionOptions = $dataField->getFieldType()->getRestrictionOptions();

        if (!empty($restrictionOptions['min']) && (null === $dataField->getRawData() ? 0 : \count($dataField->getRawData())) < $restrictionOptions['min']) {
            if (1 == $restrictionOptions['min']) {
                $dataField->addMessage('At least 1 item is required');
            } else {
                $dataField->addMessage('At least '.$restrictionOptions['min'].' items are required');
            }
            $isValid = false;
        }

        $rawData = $dataField->getRawData();

        if (!empty($restrictionOptions['max']) && \is_array($rawData) && \count($rawData) > $restrictionOptions['max']) {
            $dataField->addMessage('Too many items (max '.$restrictionOptions['max'].')');
            $isValid = false;
        }

        return $isValid;
    }

    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
                ->add('renumbering', CheckboxType::class, [
                    'required' => false,
                    'label' => 'Items will be renumbered',
                ])
                ->remove('index');
        }

        // an optional icon can't be specified ritgh to the container label
        $optionsForm->get('displayOptions')->add('singularLabel', TextType::class, [
                'required' => false,
        ])->add('itemBootstrapClass', TextType::class, [
                'required' => false,
        ])->add('labelField', TextType::class, [
                'required' => false,
        ])->add('icon', IconPickerType::class, [
                'required' => false,
        ])->add('collapsible', CheckboxType::class, [
                'required' => false,
        ])->add('sortable', CheckboxType::class, [
                'required' => false,
        ]);

        $optionsForm->get('restrictionOptions')
        ->add('min', IntegerType::class, [
                'required' => false,
        ])->add('max', IntegerType::class, [
                'required' => false,
        ]);
        $optionsForm->get('restrictionOptions')->remove('mandatory');
        $optionsForm->get('restrictionOptions')->remove('mandatory_if');
    }

    public function buildObjectArray(DataField $data, array &$out)
    {
        if (!$data->getFieldType()->getDeleted()) {
            $out[$data->getFieldType()->getName()] = [];
        }
    }

    public function getBlockPrefix()
    {
        return 'collectionfieldtype';
    }

    /**
     * @return string[]
     */
    public static function getJsonNames(FieldType $current): array
    {
        return [$current->getName()];
    }

    public function generateMapping(FieldType $current)
    {
        return [$current->getName() => [
                'type' => 'nested',
                'properties' => [],
        ]];
    }

    public function reverseViewTransform($data, FieldType $fieldType)
    {
        $cleaned = [];
        foreach ($data as $idx => $item) {
            //if the item _ems_item_reverseViewTransform is missing it means that this item hasn't been submitted (and it can be deleted)
            if (!empty($item) && isset($item['_ems_item_reverseViewTransform'])) {
                unset($item['_ems_item_reverseViewTransform']);

                //now that we know that this has been submited, let's check if it has not been marked to be deleted
                if (!isset($item['_ems_internal_deleted']) || 'deleted' != $item['_ems_internal_deleted']) {
                    unset($item['_ems_internal_deleted']);
                    if ($fieldType->getMappingOption('renumbering', false)) {
                        $cleaned[] = $item;
                    } else {
                        $cleaned[$idx] = $item;
                    }
                }
            }
        }
        $out = parent::reverseViewTransform($cleaned, $fieldType);

        return $out;
    }

    /**
     * @param string $name
     *
     * @return array<string, mixed>
     */
    public function getDefaultOptions($name): array
    {
        $out = parent::getDefaultOptions($name);
        $out['mappingOptions']['renumbering'] = true;

        return $out;
    }
}
