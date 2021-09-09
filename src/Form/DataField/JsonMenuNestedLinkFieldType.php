<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CommonBundle\Json\Decoder;
use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Service\ElasticsearchService;
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

class JsonMenuNestedLinkFieldType extends DataFieldType
{
    /** @var Decoder */
    private $decoder;
    /** @var ElasticaService */
    private $elasticaService;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        ElasticaService $elasticaService,
        Decoder $decoder
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
        $this->elasticaService = $elasticaService;
        $this->decoder = $decoder;
    }

    public function getLabel(): string
    {
        return 'JSON menu nested link field';
    }

    public static function getIcon(): string
    {
        return 'fa fa-link';
    }

    /**
     * @param DataField<DataField> $data
     * @param array<mixed>         $out
     */
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
     * @param array<mixed>                               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];
        $choices = [];
        $allowTypes = $options['json_menu_nested_types'];

        if (null !== $contentType = $fieldType->getContentType()) {
            $env = $contentType->getEnvironment();
            $index = $env ? $env->getAlias() : null;
        }

        if (!isset($index)) {
            return;
        }

        $search = $this->elasticaService->convertElasticsearchSearch([
            'index' => $index,
            'body' => $options['query'],
        ]);

        $scroll = $this->elasticaService->scroll($search);
        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                if (false === $result) {
                    continue;
                }
                $json = $result->getSource()[$options['json_menu_nested_field']] ?? false;

                if (!$json) {
                    continue;
                }

                $menu = $this->decoder->jsonMenuNestedDecode($json);

                foreach ($menu as $item) {
                    if (\count($allowTypes) > 0 && !\in_array($item->getType(), $allowTypes)) {
                        continue;
                    }

                    $label = \implode(' > ', \array_map(function (JsonMenuNested $p) {
                        return $p->getLabel();
                    }, $item->getPath()));

                    $choices[$label] = $item->getId();
                }
            }
        }

        $builder->add('value', ChoiceType::class, [
            'label' => (isset($options['label']) ? $options['label'] : $fieldType->getName()),
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
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['attr'] = [
            'data-multiple' => $options['multiple'],
            'data-expanded' => $options['expanded'],
            'class' => 'select2',
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults([
                'multiple' => false,
                'expanded' => false,
                'json_menu_nested_types' => null,
                'json_menu_nested_field' => null,
                'query' => null,
            ])
            ->setNormalizer('json_menu_nested_types', function (Options $options, $value) {
                return \explode(',', $value);
            })
        ;
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<mixed>                               $options
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('displayOptions')
            ->add('expanded', CheckboxType::class, ['required' => false])
            ->add('multiple', CheckboxType::class, ['required' => false])
            ->add('json_menu_nested_types', TextType::class, ['required' => false])
            ->add('json_menu_nested_field', TextType::class, ['required' => true])
            ->add('query', CodeEditorType::class, ['required' => false, 'language' => 'ace/mode/json'])
        ;

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
                ->add('analyzer', AnalyzerPickerType::class)
                ->add('copy_to', TextType::class, [
                    'required' => false,
                ]);
        }
    }

    /**
     * @param string $name
     *
     * @return array<mixed>
     */
    public function getDefaultOptions($name): array
    {
        $out = parent::getDefaultOptions($name);
        $out['mappingOptions']['index'] = 'not_analyzed';

        return $out;
    }

    public function getBlockPrefix(): string
    {
        return 'ems_choice';
    }

    /**
     * @param array<mixed> $data
     *
     * @return DataField<DataField>
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $value = null;
        if (isset($data['value'])) {
            $value = $data['value'];
        }

        return parent::reverseViewTransform($value, $fieldType);
    }

    /**
     * @param DataField<DataField> $dataField
     *
     * @return array<mixed>
     */
    public function viewTransform(DataField $dataField)
    {
        $temp = parent::viewTransform($dataField);

        if (empty($temp)) {
            return ['value' => []];
        }

        if (\is_string($temp)) {
            return ['value' => [$temp]];
        }

        if (\is_array($temp)) {
            $out = [];
            foreach ($temp as $item) {
                if (\is_string($item) || \is_integer($item)) {
                    $out[] = $item;
                } else {
                    $dataField->addMessage('Was not able to import the data : '.\json_encode($temp));
                }
            }

            return ['value' => $out];
        }

        $dataField->addMessage('Was not able to import the data : '.\json_encode($temp));

        return ['value' => []];
    }
}
