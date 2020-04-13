<?php

namespace EMS\CoreBundle\Form\DataField;

use Elasticsearch\Client;
use EMS\CommonBundle\Helper\Text\Decoder;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ContentTypePickerType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\ElasticsearchService;
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

    /** @var Client */
    private $client;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var Decoder */
    private $decoder;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, ContentTypeService $contentTypeService, Client $client, Decoder $decoder)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
        $this->client = $client;
        $this->contentTypeService = $contentTypeService;
        $this->decoder = $decoder;
    }

    public function getLabel()
    {
        return 'JSON menu link field';
    }
    
    public static function getIcon()
    {
        return 'fa fa-link';
    }

    public function buildObjectArray(DataField $data, array &$out)
    {
        if (! $data->getFieldType()->getDeleted()) {
            $out [$data->getFieldType()->getName()] = $data->getArrayTextValue();
        }
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $choices = [];
        if ($options['json_menu_field'] !== false && $options['json_menu_content_type'] !== false && $options['query'] !== false) {
            $contentType = $this->contentTypeService->getByName($options['json_menu_content_type']);
            $result = $this->client->search([
                'index' => $contentType->getEnvironment()->getAlias(),
                'type' => $contentType->getName(),
                'body' => $options['query'],
            ]);

            $alreadyAssignedUids = $this->collectAlreadyAssignedJsonMenuUids($fieldType, $options['raw_data'] ?? []);

            foreach ($result['hits']['hits'] as $hit) {
                $icon = $contentType->getIcon() ?? 'fa fa-file';
                $label = $hit['_id'];
                if ($contentType->getLabelField() !== null && ($hit['_source'][$contentType->getLabelField()] ?? false)) {
                    $label = htmlentities($hit['_source'][$contentType->getLabelField()]);
                }
                $label = sprintf('<i class="%s"></i> %s <span class="sr-only">(%s)</span> /', $icon, $label, $hit['_id']);
                $choices[$label] = $hit['_id'];

                $jsonMenu = $this->decoder->jsonMenuDecode($hit['_source'][$options['json_menu_field']] ?? '{}', '/');
                foreach ($jsonMenu->getUids() as $uid) {
                    if (!in_array($uid, $alreadyAssignedUids)) {
                        $choices[$label . $jsonMenu->getSlug($uid)] = $uid;
                    }
                }
            }
        }
        
        $builder->add('value', ChoiceType::class, [
                'label' => (isset($options['label']) ? $options['label'] : $fieldType->getName()),
                'required' => false,
                'disabled' => $this->isDisabled($options),
                'choices' => $choices,
                'empty_data'  => null,
                'multiple' => true,
                'expanded' => $options['expanded'],
        ]);
    }


    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars ['attr'] = [
            'data-multiple' => true,
            'data-expanded' => $options['expanded'],
            'class' => 'select2',
        ];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('expanded', false);
        $resolver->setDefault('json_menu_content_type', false);
        $resolver->setDefault('json_menu_field', false);
        $resolver->setDefault('query', false);
    }
    
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('displayOptions')->add('expanded', CheckboxType::class, [
            'required' => false,
        ])->add('json_menu_content_type', ContentTypePickerType::class, [
            'required' => false,
        ])->add('json_menu_field', TextType::class, [
            'required' => false,
        ])->add('query', CodeEditorType::class, [
            'required' => false,
            'language' => 'ace/mode/json',
        ]);

        $optionsForm->get('mappingOptions')
            ->add('analyzer', AnalyzerPickerType::class)
            ->add('copy_to', TextType::class, [
                'required' => false,
            ]);
    }

    public function getDefaultOptions($name)
    {
        $out = parent::getDefaultOptions($name);
        $out['mappingOptions']['index'] = 'not_analyzed';
        return $out;
    }
    
    public function getBlockPrefix()
    {
        return 'ems_choice';
    }
    
    
    public function reverseViewTransform($data, FieldType $fieldType)
    {
        $value = null;
        if (isset($data['value'])) {
            $value = $data['value'];
        }
        return parent::reverseViewTransform($value, $fieldType);
    }
    
    public function viewTransform(DataField $dataField)
    {
        $temp = parent::viewTransform($dataField);

        if (empty($temp)) {
            return [ 'value' => [] ];
        }

        if (is_string($temp)) {
            return [ 'value' => [$temp]];
        }

        if (is_array($temp)) {
            $out = [];
            foreach ($temp as $item) {
                if (is_string($item) || is_integer($item)) {
                    $out[] = $item;
                } else {
                    $dataField->addMessage('Was not able to import the data : ' . json_encode($temp));
                }
            }
            return [ 'value' => $out ];
        }

        $dataField->addMessage('Was not able to import the data : ' . json_encode($temp));
        return ['value' => []];
    }

    private function collectAlreadyAssignedJsonMenuUids(FieldType $fieldType, array $rawData)
    {

        $result = $this->client->search([
            'size' => 5000,
            'index' => $fieldType->getContentType()->getEnvironment()->getAlias(),
            '_source' => $fieldType->getName(),
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'term' => [ '_contenttype' => $fieldType->getContentType()->getName() ],
                            ],[
                                'exists' => [ 'field' => $fieldType->getName() ],
                            ]
                        ]
                    ],
                ],
            ],
        ]);

        $uids = [];
        foreach ($result['hits']['hits'] as $hit) {
            $uids = array_merge($uids, $hit['_source'][$fieldType->getName()] ?? []);
        }

        return array_diff($uids, $rawData[$fieldType->getName()] ?? []);
    }
}
