<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\FileService;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class UrlAttachmentFieldType extends DataFieldType
{
    /** @var FileService */
    private $fileService;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, FileService $fileService)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
        $this->fileService = $fileService;
    }

    /**
     * {@inheritdoc}
     */
    public static function getIcon()
    {
        return 'fa fa-cloud-download';
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Url Attachment (indexed) field';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return IconTextType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function reverseViewTransform($data, FieldType $fieldType)
    {
        /** @var DataField $out */
        $dataField = parent::reverseViewTransform($data, $fieldType);
        if (empty($data)) {
            if ($dataField->getFieldType()->getRestrictionOptions()['mandatory']) {
                $dataField->addMessage('This entry is required');
            }
            $dataField->setRawData(['_url' => null, '_content' => '']);
        } elseif (\is_string($data)) {
            try {
                $content = \file_get_contents($data);
                $rawData = [
                    '_url' => $data,
                    '_content' => \base64_encode($content),
                    '_size' => \strlen($content),
                ];
                $dataField->setRawData($rawData);
            } catch (\Exception $e) {
                $dataField->addMessage(\sprintf(
                    'Impossible to fetch the ressource due to %s',
                    $e->getMessage()
                ));
                $dataField->setRawData([
                        '_url' => $data,
                        '_content' => '',
                        '_size' => 0,
                ]);
            }
        } else {
            $dataField->addMessage(\sprintf(
                'Data not supported: %s',
                \json_encode($data)
            ));
        }

        return $dataField;
    }

    /**
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::modelTransform()
     */
    public function modelTransform($data, FieldType $fieldType)
    {
        if (\is_array($data)) {
            foreach ($data as $id => $content) {
                if (!\in_array($id, ['_url', '_size'], true)) {
                    unset($data[$id]);
                }
            }
        }

        return parent::reverseViewTransform($data, $fieldType);
    }

    /**
     * {@inheritdoc}
     */
    public function viewTransform(DataField $data)
    {
        $out = parent::viewTransform($data);
        if (!empty($out)) {
            if (!empty($out['_url'])) {
                if (\is_string($out['_url'])) {
                    return $out['_url'];
                }
                $data->addMessage('Non supported input data : '.\json_encode($out));
            }
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // specific mapping options
        $optionsForm->get('mappingOptions')
        ->add('analyzer', AnalyzerPickerType::class)
        ->add('copy_to', TextType::class, [
                'required' => false,
        ]);

        $optionsForm->get('displayOptions')
        ->add('icon', IconPickerType::class, [
                'required' => false,
        ])
        ->add('prefixIcon', IconPickerType::class, [
                'required' => false,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function buildObjectArray(DataField $data, array &$out)
    {
        if (!$data->getFieldType()->getDeleted()) {
            /*
             * by default it serialize the text value.
             * It can be overrided.
             */
            $out[$data->getFieldType()->getName()] = $data->getRawData();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
    }

    /**
     * {@inheritdoc}
     */
    public function generateMapping(FieldType $current, $withPipeline)
    {
        $mapping = parent::generateMapping($current, $withPipeline);
        $body = [
                'type' => 'nested',
                'properties' => [
                        '_url' => [
                            'type' => 'string',
                        ],
                        '_size' => [
                            'type' => 'long',
                        ],
                        '_content' => [
                                'type' => 'binary',
                        ],
                ],
            ];

        if ($withPipeline) {
            $body['properties']['_attachment'] = [
            //                 "type" => "nested",
                    'properties' => [
                            'content' => $mapping[$current->getName()],
                            //                     'author'=> [
                                    //                         "type" => "text",
                                    //                     ],
                            //                     'author'=> [
                                    //                             "type" => "text",
                                    //                     ],
                            //                     'content_type'=> [
                                    //                         "type" => "text",
                                    //                     ],
                            //                     'keywords'=> [
                                    //                         "type" => "text",
                                    //                     ],
                            //                     'language'=> [
                                    //                         "type" => "text",
                                    //                     ],
                            //                     'title'=> [
                                    //                         "type" => "text",
                                    //                     ]
                    ],
            ];
        }

        return [
            $current->getName() => $body,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function generatePipeline(FieldType $current)
    {
        return [
                'attachment' => [
                        'field' => $current->getName().'._content',
                        'target_field' => $current->getName().'._attachment',
                        'indexed_chars' => 1000000,
                ],
        ];
    }
}
