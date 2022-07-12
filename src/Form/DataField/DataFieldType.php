<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\ContentTypeStructureException;
use EMS\CoreBundle\Form\DataField\Options\OptionsType;
use EMS\CoreBundle\Form\Field\SelectPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * It's the mother class of all specific DataField used in eMS.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
abstract class DataFieldType extends AbstractType
{
    /** @var AuthorizationCheckerInterface */
    protected $authorizationChecker;
    /** @var FormRegistryInterface */
    protected $formRegistry;
    /** @var ElasticsearchService */
    protected $elasticsearchService;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->formRegistry = $formRegistry;
        $this->elasticsearchService = $elasticsearchService;
    }

    /**
     * @return string[]
     */
    public static function textAreaToArray(?string $textArea): array
    {
        if (null === $textArea || 0 === \strlen($textArea)) {
            return [];
        }
        $cleaned = \str_replace("\r", '', $textArea);

        return \explode("\n", $cleaned);
    }

    public function getBlockPrefix()
    {
        return 'data_field_type';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDisabled($this->isDisabled($options));
    }

    /**
     * Perfom field specfifc post-finalized treatment. It returns the children if it's a container.
     *
     * @param ?array<string, mixed> $previousData
     */
    public function postFinalizeTreatment(Revision $revision, DataField $dataField, ?array $previousData): ?array
    {
        return $previousData;
    }

    /**
     * form array to DataField.
     *
     * http://symfony.com/doc/current/form/data_transformers.html#about-model-and-view-transformers
     *
     * @param array|string|int|float|null $data
     *
     * @return \EMS\CoreBundle\Entity\DataField
     */
    public function reverseViewTransform($data, FieldType $fieldType)
    {
        $out = new DataField();

        if ((\is_string($data) && '' === $data) || (\is_array($data) && 0 === \count($data))) {
            $out->setRawData(null);
        } else {
            $out->setRawData($data);
        }
        $out->setFieldType($fieldType);

        return $out;
    }

    /**
     * datafield to form array :.
     *
     * http://symfony.com/doc/current/form/data_transformers.html#about-model-and-view-transformers
     *
     * @return array|string|int|float|null
     */
    public function viewTransform(DataField $dataField)
    {
        return $dataField->getRawData();
    }

    /**
     * datafield to raw_data array :.
     *
     * http://symfony.com/doc/current/form/data_transformers.html#about-model-and-view-transformers
     *
     * @return array|string|int|float|null
     */
    public function reverseModelTransform(DataField $dataField)
    {
        return $dataField->getRawData();
    }

    /**
     * raw_data array to datafield:.
     *
     * http://symfony.com/doc/current/form/data_transformers.html#about-model-and-view-transformers
     *
     * @param array|string|int|float|null $data
     *
     * @return DataField
     */
    public function modelTransform($data, FieldType $fieldType)
    {
        $out = new DataField();
        $out->setRawData($data);
        $out->setFieldType($fieldType);

        return $out;
    }

    /**
     * @return FormRegistryInterface
     */
    public function getFormRegistry()
    {
        return $this->formRegistry;
    }

    public function convertInput(DataField $dataField)
    {
        //by default do nothing
    }

    public function generateInput(DataField $dataField)
    {
        //by default do nothing
    }

    public function getDefaultOptions($name)
    {
        return [
            'displayOptions' => [
                'label' => SelectPickerType::humanize($name),
                'class' => 'col-md-12',
            ],
            'mappingOptions' => [
            ],
            'restrictionOptions' => [
            ],
            'extraOptions' => [
            ],
            'raw_data' => [
            ],
        ];
    }

    /**
     * Used to display in the content type edit page (instaed of the class path).
     *
     * @return string
     */
    abstract public function getLabel();

    public function isDisabled($options)
    {
        $sapiName = \php_sapi_name();
        if ('cli' === $sapiName) {
            return false;
        }

        /** @var FieldType $fieldType */
        $fieldType = $options['metadata'];

        if (\in_array($fieldType->getName(), $options['disabled_fields'] ?? [], true)) {
            return true;
        }

        $enable = ($options['migration'] && !$fieldType->getMigrationOption('protected', true)) || empty($fieldType->getMinimumRole()) || $this->authorizationChecker->isGranted($fieldType->getMinimumRole());

        return !$enable;
    }

    /**
     * Get Elasticsearch subquery.
     *
     * @return array
     */
    public function getElasticsearchQuery(DataField $dataField, array $options = [])
    {
        throw new \Exception('virtual method should be implemented by child class : '.\get_class($this));
    }

    /**
     * get the data value(s), as string, for the symfony form) in the context of this field.
     */
    public function getDataValue(DataField &$dataValues, array $options)
    {
        //TODO: should be abstract ??
        throw new \Exception('This function should never be called');
    }

    /**
     * set the data value(s) from a string recieved from the symfony form) in the context of this field.
     */
    public function setDataValue($input, DataField &$dataValues, array $options)
    {
        //TODO: should be abstract ??
        throw new \Exception('This function should never be called');
    }

    /**
     * get the list of all possible values (if it means something) filter by the values array if not empty.
     */
    public function getChoiceList(FieldType $fieldType, array $choices)
    {
        //TODO: should be abstract ??
        throw new ContentTypeStructureException('The field '.$fieldType->getName().' of the content type '.$fieldType->giveContentType()->getName().' does not have a limited list of values!');
    }

    /**
     * Get a icon to visually identify a FieldType.
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'fa fa-square';
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
                //'data_class' => 'EMS\CoreBundle\Entity\DataField',
                'lastOfRow' => false,
                'class' => null, // used to specify a bootstrap class arround the compoment
                'metadata' => null, // used to keep a link to the FieldType
                'error_bubbling' => false,
                'required' => false,
                'translation_domain' => false,
                'migration' => false,
                'with_warning' => true,
                'raw_data' => [],
                'helptext' => null,
                'disabled_fields' => [],
                'referrer-ems-id' => null,
        ]);
    }

    /**
     * See if we can asume that we should find this field directly or if its a more complex type such as file or date range.
     *
     * @deprecated
     *
     * @return bool
     */
    public static function isVirtualField(array $option)
    {
        return false;
    }

    /**
     * Assign data of the dataField based on the elastic index content ($sourceArray).
     *
     * @param string|array $sourceArray
     * @param bool         $isMigration
     *
     * @return array
     */
    public function importData(DataField $dataField, $sourceArray, $isMigration)
    {
        $migrationOptions = $dataField->getFieldType()->getMigrationOptions();
        if (!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
            $dataField->setRawData($sourceArray);
        }

        return [$dataField->getFieldType()->getName()];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Form\AbstractType::buildView()
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['class'] = $options['class'];
        $view->vars['lastOfRow'] = $options['lastOfRow'];
        $view->vars['helptext'] = $options['helptext'];
        $view->vars['isContainer'] = $this->isContainer();
        if (null == $options['label']) {
            $view->vars['label'] = false;
        }
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $form->getConfig()->getType()->getInnerType();
        if ($form->getErrors()->count() > 0 && !$dataFieldType->isContainer() && $form->has('value')) {
            foreach ($form->getErrors() as $error) {
                $form->get('value')->addError($error);
            }
        }
    }

    /**
     * Build an array representing the object, this array is ready to be serialized in json
     * and push in elasticsearch.
     */
    public function buildObjectArray(DataField $data, array &$out)
    {
        if (!$data->getFieldType()->getDeleted()) {
            /*
             * by default it serialize the text value.
             * It can be overrided.
             */
            $out[$data->getFieldType()->getName()] = $data->getTextValue();
        }
    }

    /**
     * Test if the field may contain sub field.
     *
     * I.e. container, nested, array, ...
     *
     * @return bool
     */
    public static function isContainer()
    {
        return false;
    }

    public static function isNested()
    {
        return false;
    }

    public static function isCollection()
    {
        return false;
    }

    /**
     * Should the children be mapped?
     */
    public static function hasMappedChildren(): bool
    {
        return true;
    }

    /**
     * Test if the field is valid.
     *
     * @return bool
     */
    public function isValid(DataField &$dataField, DataField $parent = null, &$masterRawData = null)
    {
        if ($this->hasDeletedParent($parent)) {
            return true;
        }

        return 0 === \count($dataField->getMessages()) && $this->isMandatory($dataField, $parent, $masterRawData);
    }

    /**
     * Test if the requirment of the field is reached.
     *
     * @return bool
     */
    public function isMandatory(DataField &$dataField, DataField $parent = null, &$masterRawData = null)
    {
        $isValidMandatory = true;
        //Get FieldType mandatory option
        $restrictionOptions = $dataField->getFieldType()->getRestrictionOptions();
        if (isset($restrictionOptions['mandatory']) && true == $restrictionOptions['mandatory']) {
            if (null === $parent || !isset($restrictionOptions['mandatory_if']) || null === $parent->getRawData() || !empty($this->resolve($masterRawData ?? [], $parent->getRawData(), $restrictionOptions['mandatory_if']))) {
                //Get rawData
                $rawData = $dataField->getRawData();
                if (null === $rawData || (\is_string($rawData) && '' === $rawData) || (\is_array($rawData) && 0 === \count($rawData))) {
                    $isValidMandatory = false;
                    $dataField->addMessage('Empty field');
                }
            }
        }

        return $isValidMandatory;
    }

    public static function resolve(array $rawData, array $parentRawData, $path, $default = null)
    {
        $current = $rawData;
        if (\strlen($path) && '.' === \substr($path, 0, 1)) {
            $current = $parentRawData;
        }

        $p = \strtok($path, '.');
        while (false !== $p) {
            if (!isset($current[$p])) {
                return $default;
            }
            $current = $current[$p];
            $p = \strtok('.');
        }

        return $current;
    }

    public function hasDeletedParent(DataField $parent = null)
    {
        if (!$parent) {
            return false;
        }
        if (isset($parent->getRawData()['_ems_internal_deleted']) && 'deleted' == $parent->getRawData()['_ems_internal_deleted']) {
            return true;
        }

        return $parent->getParent() ? $this->hasDeletedParent($parent->getParent()) : false;
    }

    /**
     * Build a Field specific options sub-form (or compount field) (used in edit content type).
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('options', OptionsType::class, ['field_type' => $options['data']]);
    }

    /**
     * return true if the field exist as is in elasticsearch.
     *
     * @return bool
     */
    public static function isVirtual(array $option = [])
    {
        return false;
    }

    /**
     * return an array filtered with subfields foir this specfic fieldtype (in case of virtualfield wich is not a container (datarange)).
     *
     * @throws \Exception
     *
     * @return array
     */
    public static function filterSubField(array $data, array $option)
    {
        throw new \Exception('Only a non-container datafield which is virtual (i.e. a non-nested datarange) can be filtered');
    }

    /**
     * @return string[]
     */
    public static function getJsonNames(FieldType $current): array
    {
        return [$current->getName()];
    }

    /**
     * Build an elasticsearch mapping options as an array.
     *
     * @return array
     */
    public function generateMapping(FieldType $current)
    {
        $options = $this->elasticsearchService->updateMapping(\array_merge(['type' => 'string'], \array_filter($current->getMappingOptions())));

        return [$current->getName() => $options];
    }
}
