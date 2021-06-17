<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\FileType;
use EMS\CoreBundle\Form\Field\IconPickerType;
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
class IndexedAssetFieldType extends DataFieldType
{
    /** @var FileService */
    private $fileService;

    /**
     * {@inheritdoc}
     */
    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, FileService $fileService)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
        $this->fileService = $fileService;
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

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Indexed file field';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return FileType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

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

    /**
     * {@inheritdoc}
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

        return [
            $current->getName() => [
                    'type' => 'nested',
                    'properties' => [
                            EmsFields::CONTENT_MIME_TYPE_FIELD => $this->elasticsearchService->getKeywordMapping(),
                            EmsFields::CONTENT_FILE_HASH_FIELD => $this->elasticsearchService->getKeywordMapping(),
                            EmsFields::CONTENT_FILE_NAME_FIELD => $this->elasticsearchService->getIndexedStringMapping(),
                            EmsFields::CONTENT_FILE_SIZE_FIELD => $this->elasticsearchService->getLongMapping(),
                            '_content' => $mapping[$current->getName()],
                    ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::reverseViewTransform()
     */
    public function reverseViewTransform($data, FieldType $fieldType)
    {
        $dataField = parent::reverseViewTransform($data, $fieldType);
        $this->testDataField($dataField);

        return $dataField;
    }

    private function testDataField(DataField $dataField)
    {
        $raw = $dataField->getRawData();

        if ((empty($raw) || empty($raw['sha1']))) {
            if (isset($dataField->getFieldType()->getRestrictionOptions()['mandatory']) && $dataField->getFieldType()->getRestrictionOptions()['mandatory']) {
                $dataField->addMessage('This entry is required');
            }
            $dataField->setRawData(null);
        } elseif (!$this->fileService->head($raw['sha1'])) {
            $dataField->addMessage('File not found on the server try to re-upload it');
        } else {
            $raw['filesize'] = $this->fileService->getSize($raw['sha1']);
            $dataField->setRawData($raw);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
     */
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        if (empty($out['sha1'])) {
            $out = null;
        }

        return $out;
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
                if (!\in_array($id, ['sha1', 'filename', 'filesize', 'mimetype', '_date', '_author', '_language', '_content', '_title'], true)) {
                    unset($data[$id]);
                } elseif ('sha1' !== $id && empty($data[$id])) {
                    unset($data[$id]);
                }
            }
        }

        return parent::reverseViewTransform($data, $fieldType);
    }
}
