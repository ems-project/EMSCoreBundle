<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\AssetType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
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
class FileAttachmentFieldType extends DataFieldType
{
    /** @var FileService */
    private $fileService;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, FileService $fileService, LoggerInterface $logger)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
        $this->fileService = $fileService;
        $this->logger = $logger;
    }

    /**
     * Get a icon to visually identify a FieldType.
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'fa fa-file-text-o';
    }

    public function getLabel()
    {
        return 'File Attachment (indexed) field';
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['metadata'];
        $builder->add('value', AssetType::class, [
                'label' => (null != $options['label'] ? $options['label'] : $fieldType->getName()),
                'disabled' => $this->isDisabled($options),
                'required' => false,
        ]);
    }

    public function modelTransform($data, FieldType $fieldType)
    {
        if (\is_array($data)) {
            foreach ($data as $id => $content) {
                if (!\in_array($id, ['sha1', 'filename', 'mimetype'], true)) {
                    unset($data[$id]);
                }
            }
        }

        return parent::reverseViewTransform($data, $fieldType);
    }

    public function reverseViewTransform($data, FieldType $fieldType)
    {
        $dataField = parent::reverseViewTransform($data, $fieldType);
        if (!empty($dataField->getRawData()) && !empty($dataField->getRawData()['value']['sha1'])) {
            $rawData = $dataField->getRawData()['value'];
            $rawData['content'] = $this->fileService->getBase64($rawData['sha1']);
            if (!$rawData['content']) {
                $this->logger->warning('form.data_field.file_attachment.file_not_found', [
                    'file_hash' => $rawData[EmsFields::CONTENT_FILE_HASH_FIELD],
                ]);
                $rawData['content'] = '';
            }
            $rawData['filesize'] = $this->fileService->getSize($rawData['sha1']);
            if (!$rawData['filesize']) {
                $rawData['filesize'] = 0;
            }

            $dataField->setRawData($rawData);
        } else {
            $dataField->setRawData(['content' => '']);
        }

        return $dataField;
    }

    public function getBlockPrefix()
    {
        return 'bypassdatafield';
    }

    public function viewTransform(DataField $dataField)
    {
        $rawData = $dataField->getRawData();

        if (!empty($rawData) && !empty($rawData['sha1'])) {
            unset($rawData['content']);
            unset($rawData['filesize']);
        } else {
            $rawData = [];
        }

        return ['value' => $rawData];
    }

    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        //         $optionsForm->remove ( 'mappingOptions' );

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
            ->add('analyzer', AnalyzerPickerType::class)
            ->add('copy_to', TextType::class, [
                    'required' => false,
            ]);
        }

        $optionsForm->get('displayOptions')
            ->add('icon', IconPickerType::class, [
                    'required' => false,
            ])
            ->add('imageAssetConfigIdentifier', TextType::class, [
                    'required' => false,
            ]);
    }

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

    public function configureOptions(OptionsResolver $resolver)
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('imageAssetConfigIdentifier', null);
    }

    public function generateMapping(FieldType $current, $withPipeline)
    {
        $mapping = parent::generateMapping($current, $withPipeline);
        $body = [
                'type' => 'nested',
                'properties' => [
                        EmsFields::CONTENT_MIME_TYPE_FIELD => $this->elasticsearchService->getKeywordMapping(),
                        EmsFields::CONTENT_FILE_HASH_FIELD => $this->elasticsearchService->getKeywordMapping(),
                        EmsFields::CONTENT_FILE_NAME_FIELD => $this->elasticsearchService->getIndexedStringMapping(),
                        EmsFields::CONTENT_FILE_SIZE_FIELD => $this->elasticsearchService->getLongMapping(),
                        'content' => [
                            'type' => 'binary',
                        ],
                ],
            ];

        if ($withPipeline) {
//             $body['properties']['content'] = [
//                     "type" => "text",
//                     "index" => "no",
//                     'fields' => [
//                             'keyword' => [
//                                     'type' => 'keyword',
//                                     'ignore_above' => 256
//                             ]
//                     ]
//             ];

            $body['properties']['attachment'] = [
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

    public static function generatePipeline(FieldType $current)
    {
        return [
            'attachment' => [
                'field' => $current->getName().'.content',
                'target_field' => $current->getName().'.attachment',
                'indexed_chars' => 1000000,
            ],
        ];
    }
}
