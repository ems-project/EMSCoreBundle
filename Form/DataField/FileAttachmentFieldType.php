<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AssetType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\FileService;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Form\FormRegistryInterface;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Session\Session;
    
/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class FileAttachmentFieldType extends DataFieldType
{
    
    /**@var FileService */
    private $fileService;
    /**@var Session */
    private $session;
    
    
    
    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, FileService $fileService, Session $session)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
        $this->fileService= $fileService;
        $this->session = $session;
    }

    /**
     * Get a icon to visually identify a FieldType
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'fa fa-file-text-o';
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function getLabel()
    {
        return 'File Attachment (indexed) field';
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var FieldType $fieldType */
        $fieldType = $options ['metadata'];
        $builder->add('value', AssetType::class, [
                'label' => (null != $options ['label']?$options ['label']:$fieldType->getName()),
                'disabled'=> $this->isDisabled($options),
                'required' => false,
        ]);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::modelTransform()
     */
    public function modelTransform($data, FieldType $fieldType)
    {
        if (is_array($data)) {
            foreach ($data as $id => $content) {
                if (! in_array($id, ['sha1', 'filename', 'mimetype'], true)) {
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
                $this->session->getFlashBag()->add('warning', 'File not found: '.$rawData['sha1']);
                $rawData['content'] = "";
            }
            $rawData['filesize'] = $this->fileService->getSize($rawData['sha1']);
            if (!$rawData['filesize']) {
                $rawData['filesize'] = 0;
            }
            
            $dataField->setRawData($rawData);
        } else {
            $dataField->setRawData(['content' => ""]);
        }
        return $dataField;
    }
    
    
    
    /**
     *
     * {@inheritDoc}
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::getBlockPrefix()
     */
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
    
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        //         $optionsForm->remove ( 'mappingOptions' );
        
        // specific mapping options
        $optionsForm->get('mappingOptions')
        ->add('analyzer', AnalyzerPickerType::class)
        ->add('copy_to', TextType::class, [
                'required' => false,
        ]);
        
        $optionsForm->get('displayOptions')
            ->add('icon', IconPickerType::class, [
                    'required' => false
            ])
            ->add('imageAssetConfigIdentifier', TextType::class, [
                    'required' => false,
            ]);
    }


    /**
     *
     * {@inheritdoc}
     *
     */
    public static function buildObjectArray(DataField $data, array &$out)
    {
        if (! $data->getFieldType()->getDeleted()) {
            /**
             * by default it serialize the text value.
             * It can be overrided.
             */
            $out [$data->getFieldType()->getName()] = $data->getRawData();
        }
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('imageAssetConfigIdentifier', null);
    }
    
    /**
     * {@inheritdoc}
     */
    public function generateMapping(FieldType $current, $withPipeline)
    {
        $mapping = parent::generateMapping($current, $withPipeline);
        $body = [
                "type" => "nested",
                "properties" => [
                        "mimetype" => $this->elasticsearchService->getKeywordMapping(),
                        "sha1" => $this->elasticsearchService->getKeywordMapping(),
                        "filename" => $this->elasticsearchService->getIndexedStringMapping(),
                        "filesize" => $this->elasticsearchService->getLongMapping(),
                        'content' => [
                            "type" => "binary",
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
                "properties" => [
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
                ]
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
            "attachment" => [
                'field' => $current->getName().'.content',
                'target_field' => $current->getName().'.attachment',
                'indexed_chars' => 1000000,
            ]
        ];
    }
}
