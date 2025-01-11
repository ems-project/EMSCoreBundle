<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Core\ContentType\DataFieldFormOptions;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\Options\OptionsType;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\Helpers\Standard\Text;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
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
    private ?DataFieldFormOptions $formOptions = null;

    public function __construct(
        protected AuthorizationCheckerInterface $authorizationChecker,
        protected FormRegistryInterface $formRegistry,
        protected ElasticsearchService $elasticsearchService,
    ) {
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

    public static function isVisible(): bool
    {
        return true;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'data_field_type';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDisabled($this->isDisabled($options));
    }

    /**
     * Perfom field specfifc post-finalized treatment. It returns the children if it's a container.
     */
    public function postFinalizeTreatment(string $type, string $id, DataField $dataField, mixed $previousData): mixed
    {
        return $previousData;
    }

    /**
     * form array to DataField.
     *
     * http://symfony.com/doc/current/form/data_transformers.html#about-model-and-view-transformers
     *
     * @param array<mixed>|string|int|float|bool|null $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $out = new DataField();
        $out
            ->setFieldType($fieldType)
            ->setFormOptions($this->formOptions);

        if ('' === $data || (\is_array($data) && 0 === \count($data))) {
            $out->setRawData(null);
        } else {
            $out->setRawData($data);
        }

        return $out;
    }

    /**
     * datafield to form array :.
     *
     * http://symfony.com/doc/current/form/data_transformers.html#about-model-and-view-transformers
     *
     * @return array<mixed>|string|int|float|bool|null
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
     * @return array<mixed>|string|int|float|bool|null
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
     * @param array<mixed>|string|int|float|bool|null $data
     */
    public function modelTransform($data, FieldType $fieldType): DataField
    {
        $out = new DataField();
        $out
            ->setRawData($data)
            ->setFieldType($fieldType)
            ->setFormOptions($this->formOptions);

        return $out;
    }

    public function getFormRegistry(): FormRegistryInterface
    {
        return $this->formRegistry;
    }

    public function convertInput(DataField $dataField): void
    {
        // by default do nothing
    }

    public function generateInput(DataField $dataField): void
    {
        // by default do nothing
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultOptions(string $name): array
    {
        return [
            'displayOptions' => [
                'label' => Text::humanize($name),
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
     */
    abstract public function getLabel(): string;

    /**
     * @param array<string, mixed> $options
     */
    public function isDisabled(array $options): bool
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
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    public function getElasticsearchQuery(DataField $dataField, array $options = []): array
    {
        return [];
    }

    /**
     * get the data value(s), as string, for the symfony form) in the context of this field.
     *
     * @param array<mixed> $options
     */
    public function getDataValue(DataField &$dataValues, array $options): never
    {
        // TODO: should be abstract ??
        throw new \Exception('This function should never be called');
    }

    /**
     * set the data value(s) from a string recieved from the symfony form) in the context of this field.
     *
     * @param array<mixed> $options
     */
    public function setDataValue(mixed $input, DataField &$dataValues, array $options): never
    {
        // TODO: should be abstract ??
        throw new \Exception('This function should never be called');
    }

    /**
     * get the list of all possible values (if it means something) filter by the values array if not empty.
     *
     * @param array<mixed> $choices
     *
     * @return array<mixed>
     */
    public function getChoiceList(FieldType $fieldType, array $choices): array
    {
        return [];
    }

    public static function getIcon(): string
    {
        return 'fa fa-square';
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // 'data_class' => 'EMS\CoreBundle\Entity\DataField',
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
            'is_visible' => true,
        ]);
    }

    /**
     * See if we can asume that we should find this field directly or if its a more complex type such as file or date range.
     *
     * @param array<mixed> $option
     *
     * @deprecated
     */
    public static function isVirtualField(array $option): bool
    {
        return false;
    }

    /**
     * @param array<mixed>|string|int|float|bool|null $sourceArray
     *
     * @return array<mixed>
     */
    public function importData(DataField $dataField, array|string|int|float|bool|null $sourceArray, bool $isMigration): array
    {
        $migrationOptions = $dataField->giveFieldType()->getMigrationOptions();
        if (!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
            $dataField->setRawData($sourceArray);
        }

        return [$dataField->giveFieldType()->getName()];
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['class'] = $options['class'];
        $view->vars['lastOfRow'] = $options['lastOfRow'];
        $view->vars['helptext'] = $options['helptext'];
        $view->vars['isContainer'] = static::isContainer();
        $view->vars['isVisible'] = static::isVisible();
        if (null == $options['label']) {
            $view->vars['label'] = false;
        }
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $form->getConfig()->getType()->getInnerType();
        if ($form->getErrors()->count() > 0 && !$dataFieldType->isContainer() && $form->has('value')) {
            foreach ($form->getErrors() as $error) {
                if ($error instanceof FormError) {
                    $form->get('value')->addError($error);
                }
            }
        }
    }

    /**
     * Build an array representing the object, this array is ready to be serialized in json
     * and push in elasticsearch.
     *
     * @param array<string, mixed> $out
     */
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            /*
             * by default it serialize the text value.
             * It can be overrided.
             */
            $out[$data->giveFieldType()->getName()] = $data->getTextValue();
        }
    }

    /**
     * Test if the field may contain sub field.
     *
     * I.e. container, nested, array, ...
     */
    public static function isContainer(): bool
    {
        return false;
    }

    public static function isNested(): bool
    {
        return false;
    }

    public static function isCollection(): bool
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
     */
    public function isValid(DataField &$dataField, ?DataField $parent = null, mixed &$masterRawData = null): bool
    {
        if ($this->hasDeletedParent($parent)) {
            return true;
        }

        return 0 === \count($dataField->getMessages()) && $this->isMandatory($dataField, $parent, $masterRawData);
    }

    /**
     * Test if the requirment of the field is reached.
     */
    public function isMandatory(DataField &$dataField, ?DataField $parent = null, mixed &$masterRawData = null): bool
    {
        $isValidMandatory = true;
        // Get FieldType mandatory option
        $restrictionOptions = $dataField->giveFieldType()->getRestrictionOptions();
        if (isset($restrictionOptions['mandatory']) && true == $restrictionOptions['mandatory']) {
            $parentRawData = $parent ? $parent->getRawData() : [];
            $parentRawDataArray = \is_array($parentRawData) ? $parentRawData : [];

            if (null === $parent || !isset($restrictionOptions['mandatory_if'])
                || null === $parent->getRawData()
                || !empty(static::resolve($masterRawData ?? [], $parentRawDataArray, $restrictionOptions['mandatory_if']))) {
                // Get rawData
                $rawData = $dataField->getRawData();
                if (null === $rawData || (\is_string($rawData) && '' === $rawData) || (\is_array($rawData) && 0 === \count($rawData))) {
                    $isValidMandatory = false;
                    $dataField->addMessage('Empty field');
                }
            }
        }

        return $isValidMandatory;
    }

    /**
     * @param array<mixed> $rawData
     * @param array<mixed> $parentRawData
     */
    public static function resolve(array $rawData, array $parentRawData, string $path, ?string $default = null): ?string
    {
        $current = $rawData;
        if (\strlen($path) && \str_starts_with($path, '.')) {
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

    public function hasDeletedParent(?DataField $parent = null): bool
    {
        if (!$parent) {
            return false;
        }

        $parentRawData = $parent->getRawData();

        if (\is_array($parentRawData) && isset($parentRawData['_ems_internal_deleted']) && 'deleted' == $parentRawData['_ems_internal_deleted']) {
            return true;
        }

        return $parent->getParent() && $this->hasDeletedParent($parent->getParent());
    }

    /**
     * Build a Field specific options sub-form (or compount field) (used in edit content type).
     *
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('options', OptionsType::class, ['field_type' => $options['data']]);
    }

    /**
     * return true if the field exist as is in elasticsearch.
     *
     * @param array<mixed> $option
     */
    public static function isVirtual(array $option = []): bool
    {
        return false;
    }

    /**
     * return an array filtered with subfields foir this specfic fieldtype (in case of virtualfield wich is not a container (datarange)).
     *
     * @param array<mixed> $data
     * @param array<mixed> $option
     *
     * @return array<mixed>
     */
    public static function filterSubField(array $data, array $option): array
    {
        return [];
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
     * @return array<mixed>
     */
    public function generateMapping(FieldType $current): array
    {
        $options = $this->elasticsearchService->updateMapping(\array_merge(['type' => 'string'], \array_filter($current->getMappingOptions())));

        return [$current->getName() => $options];
    }

    protected function buildChildForm(FieldType $fieldType, mixed $options, FormBuilderInterface $builder): void
    {
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
                'locale' => $options['locale'],
            ], $fieldType->getDisplayOptions());

            $builder->add($fieldType->getName(), $fieldType->getType(), $options);

            $childField = $builder->get($fieldType->getName());
            $childField
                ->addViewTransformer(new DataFieldViewTransformer($fieldType, $this->formRegistry))
                ->addModelTransformer(DataFieldModelTransformer::withFormOptions(
                    fieldType: $fieldType,
                    formRegistry: $this->formRegistry,
                    viewOptions: new DataFieldFormOptions(
                        locale: $childField->getOption('locale', $options['locale']),
                        referredEmsId: $childField->getOption('referrer-ems-id', $options['referrer-ems-id']),
                    )
                ));
        }
    }

    public function setFormOptions(?DataFieldFormOptions $formOptions): void
    {
        $this->formOptions = $formOptions;
    }
}
