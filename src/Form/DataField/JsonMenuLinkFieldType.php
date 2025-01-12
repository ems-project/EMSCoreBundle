<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CommonBundle\Json\Decoder;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ContentTypePickerType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class JsonMenuLinkFieldType extends DataFieldType
{
    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, private readonly ContentTypeService $contentTypeService, private readonly ElasticaService $elasticaService, private readonly Decoder $decoder)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'JSON menu link field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-link';
    }

    #[\Override]
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            $out[$data->giveFieldType()->getName()] = $data->getArrayTextValue();
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

        $choices = [];
        if (false !== $options['json_menu_field'] && false !== $options['json_menu_content_type'] && false !== $options['query']) {
            $contentType = $this->contentTypeService->giveByName($options['json_menu_content_type']);

            $search = $this->elasticaService->convertElasticsearchSearch([
                'index' => $contentType->giveEnvironment()->getAlias(),
                'type' => $contentType->getName(),
                'body' => $options['query'],
            ]);

            $isMigration = $options['migration'] ?? false;
            $alreadyAssignedUuids = !$isMigration ? $this->collectAlreadyAssignedJsonUuids($fieldType, $options['raw_data'] ?? []) : [];

            $scroll = $this->elasticaService->scroll($search);
            foreach ($scroll as $resultSet) {
                foreach ($resultSet as $result) {
                    $icon = $contentType->getIcon() ?? 'fa fa-file';
                    $label = $result->getId();
                    if (null !== $contentType->getLabelField() && ($result->getSource()[$contentType->getLabelField()] ?? false)) {
                        $label = \htmlentities((string) $result->getSource()[$contentType->getLabelField()]);
                    }
                    $label = \sprintf('<i class="%s"></i> %s <span class="sr-only">(%s)</span> /', $icon, $label, $result->getId());

                    if ($options['allow_link_to_root'] ?? false) {
                        $choices[$label] = $result->getId();
                    }

                    $jsonMenu = $this->decoder->jsonMenuDecode($result->getSource()[$options['json_menu_field']] ?? '{}', '/');
                    foreach ($jsonMenu->getUids() as $uid) {
                        if (!\in_array($uid, $alreadyAssignedUuids)) {
                            if (($jsonMenu->getItem($uid)['contentType'] ?? false) === $fieldType->giveContentType()->getName()) {
                                $choices[$label.$jsonMenu->getSlug($uid)] = $uid;
                            }
                        }
                    }
                }
            }
        }

        $builder->add('value', ChoiceType::class, [
            'label' => ($options['label'] ?? $fieldType->getName()),
            'required' => false,
            'disabled' => $this->isDisabled($options),
            'choices' => $choices,
            'empty_data' => [],
            'multiple' => true,
            'expanded' => $options['expanded'],
        ]);
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['attr'] = [
            'data-multiple' => true,
            'data-expanded' => $options['expanded'],
            'class' => 'select2',
        ];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('expanded', false);
        $resolver->setDefault('allow_link_to_root', false);
        $resolver->setDefault('json_menu_content_type', false);
        $resolver->setDefault('json_menu_field', false);
        $resolver->setDefault('query', false);
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('displayOptions')->add('expanded', CheckboxType::class, [
            'required' => false,
        ])->add('allow_link_to_root', CheckboxType::class, [
            'required' => false,
        ])->add('json_menu_content_type', ContentTypePickerType::class, [
            'required' => false,
        ])->add('json_menu_field', TextType::class, [
            'required' => false,
        ])->add('query', CodeEditorType::class, [
            'required' => false,
            'language' => 'ace/mode/json',
        ]);

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

        return ['value' => $out];
    }

    /**
     * @param array<mixed> $rawData
     *
     * @return array<mixed>
     */
    private function collectAlreadyAssignedJsonUuids(FieldType $fieldType, array $rawData): array
    {
        $search = $this->elasticaService->convertElasticsearchSearch([
            'size' => 500,
            'index' => $fieldType->giveContentType()->giveEnvironment()->getAlias(),
            '_source' => $fieldType->getName(),
            'type' => $fieldType->giveContentType()->getName(),
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'exists' => ['field' => $fieldType->getName()],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $uuids = [];
        $scroll = $this->elasticaService->scroll($search);
        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                $uuids = \array_merge($uuids, $result->getSource()[$fieldType->getName()] ?? []);
            }
        }

        return \array_diff($uuids, $rawData[$fieldType->getName()] ?? []);
    }
}
