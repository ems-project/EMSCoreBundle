<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CommonBundle\Elasticsearch\Response\Response;
use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\EnvironmentPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

class JsonMenuNestedLinkFieldType extends DataFieldType
{
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        private readonly ElasticaService $elasticaService,
        private readonly EnvironmentService $environmentService,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'JSON menu nested link field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-link';
    }

    #[\Override]
    public function buildObjectArray(DataField $data, array &$out): void
    {
        $fieldType = $data->getFieldType();

        if (null === $fieldType) {
            return;
        }

        if (!$fieldType->getDeleted()) {
            $out[$fieldType->getName()] = $data->getArrayTextValue();
        }
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $choices = $this->buildChoices(
            $fieldType,
            jmnQuery: $options['query'],
            jmnField: $options['json_menu_nested_field'],
            jmnChoicesTemplate: $options['choices_template'],
            jmnTypes: $options['json_menu_nested_types'] ?? [],
            jmnUnique: $options['json_menu_nested_unique'],
            rawData: $options['raw_data'] ?? [],
            migration: $options['migration'] ?? false
        );

        $builder->add('value', ChoiceType::class, [
            'label' => ($options['label'] ?? $fieldType->getName()),
            'required' => false,
            'disabled' => $this->isDisabled($options),
            'choices' => $choices,
            'empty_data' => $options['multiple'] ? [] : null,
            'multiple' => $options['multiple'],
            'expanded' => $options['expanded'],
        ]);
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<mixed>                 $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['attr'] = [
            'data-multiple' => $options['multiple'],
            'data-expanded' => $options['expanded'],
            'class' => 'select2',
        ];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults([
                'multiple' => false,
                'expanded' => false,
                'json_menu_nested_types' => null,
                'json_menu_nested_field' => null,
                'json_menu_nested_unique' => false,
                'query' => null,
                'environment' => null,
                'choices_template' => null,
                'display_template' => null,
            ])
            ->setNormalizer('json_menu_nested_types', fn (Options $options, $value) => \explode(',', (string) $value))
        ;
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('displayOptions')
            ->add('expanded', CheckboxType::class, ['required' => false])
            ->add('multiple', CheckboxType::class, ['required' => false])
            ->add('json_menu_nested_types', TextType::class, ['required' => false])
            ->add('json_menu_nested_field', TextType::class, ['required' => true])
            ->add('json_menu_nested_unique', CheckboxType::class, ['required' => false])
            ->add('query', CodeEditorType::class, ['required' => false, 'language' => 'ace/mode/json'])
            ->add('environment', EnvironmentPickerType::class, [
                'required' => false,
                'managedOnly' => false,
                'userPublishEnvironments' => false,
            ])
            ->add('choices_template', CodeEditorType::class, ['required' => false, 'min-lines' => 10, 'language' => 'ace/mode/twig'])
            ->add('display_template', CodeEditorType::class, ['required' => false, 'min-lines' => 10, 'language' => 'ace/mode/twig'])
        ;

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
                ->add('analyzer', AnalyzerPickerType::class)
                ->add('copy_to', TextType::class, [
                    'required' => false,
                ]);
        }
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'ems_choice';
    }

    /**
     * @param array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $value = null;
        if (isset($data['value'])) {
            $value = $data['value'];
        }

        return parent::reverseViewTransform($value, $fieldType);
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $temp = parent::viewTransform($dataField);
        $out = [];
        if ($dataField->giveFieldType()->getDisplayOption('multiple', false)) {
            if (empty($temp)) {
                $out = [];
            } elseif (\is_string($temp)) {
                $out = [$temp];
            } elseif (\is_array($temp)) {
                $out = [];
                foreach ($temp as $item) {
                    if (\is_string($item) || \is_integer($item)) {
                        $out[] = $item;
                    } else {
                        $dataField->addMessage('Was not able to import the data : '.Json::encode($item));
                    }
                }
            } else {
                $dataField->addMessage('Was not able to import the data : '.Json::encode($out));
                $out = [];
            }
        } else { // not mutiple
            if (null === $temp) {
                $out = null;
            } elseif (\is_string($temp) || \is_integer($temp)) {
                $out = $temp;
            } elseif (\is_array($temp) && null != $temp && (\is_string(\array_values($temp)[0]) || \is_integer(\array_values($temp)[0]))) {
                $out = \array_values($temp)[0];
                $dataField->addMessage('Only the first item has been imported : '.Json::encode($temp));
            } else {
                $dataField->addMessage('Was not able to import the data : '.Json::encode($temp));
                $out = [];
            }
        }

        return ['value' => $out];
    }

    /**
     * @param string[]             $jmnTypes
     * @param array<string, mixed> $rawData
     *
     * @return array<string, string>
     */
    private function buildChoices(
        FieldType $fieldType,
        ?string $jmnQuery = null,
        ?string $jmnField = null,
        ?string $jmnChoicesTemplate = null,
        array $jmnTypes = [],
        bool $jmnUnique = false,
        array $rawData = [],
        bool $migration = false,
    ): array {
        if (null === $jmnQuery || null === $jmnField) {
            return [];
        }

        $assignedUuids = !$migration && $jmnUnique ? $this->searchAssignedUuids($fieldType, $rawData) : [];

        $environmentName = $fieldType->getDisplayOption('environment');
        $environment = $environmentName ? $this->environmentService->giveByName($environmentName) : null;

        $index = $environment ? $environment->getAlias() : $fieldType->giveContentType()->giveEnvironment()->getAlias();
        $jmnMenu = $this->createJsonMenuNested($index, $jmnQuery, $jmnField);

        $items = [];
        foreach ($jmnMenu->getChildren() as $structure) {
            foreach ($structure as $item) {
                if (\in_array($item->getId(), $assignedUuids, true)) {
                    continue;
                }
                if (\count($jmnTypes) > 0 && !\in_array($item->getType(), $jmnTypes, true)) {
                    continue;
                }
                $items[] = $item;
            }
        }

        if ($jmnChoicesTemplate) {
            try {
                $twigChoicesTemplate = $this->twig->createTemplate($jmnChoicesTemplate, $fieldType->getPath());

                return Json::decode($twigChoicesTemplate->render(['items' => $items]));
            } catch (\Throwable $error) {
                $this->logger->error(\sprintf('Render choices template failed: "%s"', $error->getMessage()), [
                    'trace' => $error->getTraceAsString(),
                ]);
            }
        }

        $choices = [];
        foreach ($items as $item) {
            $label = \implode(' > ', \array_map(static fn (JsonMenuNested $p) => $p->getLabel(), $item->getPath()));
            $choices[$label] = $item->getId();
        }

        return $choices;
    }

    private function createJsonMenuNested(string $index, string $jmnQuery, string $jmnField): JsonMenuNested
    {
        $search = $this->elasticaService->convertElasticsearchSearch([
            'size' => 5000,
            'index' => $index,
            'body' => $jmnQuery,
        ]);

        $structures = [];

        $response = Response::fromArray($this->elasticaService->search($search)->getResponse()->getData());
        foreach ($response->getDocuments() as $document) {
            $source = $document->getSource();
            $object = \array_filter($source, static fn ($key) => $key !== $jmnField, ARRAY_FILTER_USE_KEY);

            $structures[] = [
                'id' => $document->getId(),
                'type' => $document->getContentType(),
                'label' => $document->getContentType(),
                'object' => $object,
                'children' => Json::decode($source[$jmnField] ?? '{}'),
            ];
        }

        return JsonMenuNested::fromStructure($structures);
    }

    /**
     * @param array<mixed> $rawData
     *
     * @return array<mixed>
     */
    private function searchAssignedUuids(FieldType $fieldType, array $rawData): array
    {
        $search = $this->elasticaService->convertElasticsearchSearch([
            'size' => 500,
            'index' => $fieldType->giveContentType()->giveEnvironment()->getAlias(),
            '_source' => $fieldType->getName(),
            'body' => ['query' => ['exists' => ['field' => $fieldType->getName()]]],
        ]);

        $uuids = [];
        $scroll = $this->elasticaService->scroll($search);
        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                $sourceValue = $result->getSource()[$fieldType->getName()] ?? null;
                if ($sourceValue) {
                    $mergeValue = \is_array($sourceValue) ? $sourceValue : [$sourceValue];
                    $uuids = [...$uuids, ...$mergeValue];
                }
            }
        }

        $rawDataValue = $rawData[$fieldType->getName()] ?? null;

        return $rawDataValue ?
            \array_diff($uuids, \is_array($rawDataValue) ? $rawDataValue : [$rawDataValue]) : $uuids;
    }
}
