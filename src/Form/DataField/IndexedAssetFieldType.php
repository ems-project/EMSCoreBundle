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
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        private readonly FileService $fileService
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
    }

    public static function getIcon(): string
    {
        return 'fa fa-file-text-o';
    }

    public function getLabel(): string
    {
        return 'Indexed file field';
    }

    public function getParent(): string
    {
        return FileType::class;
    }

    /**
     * {@inheritDoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('imageAssetConfigIdentifier', null);
    }

    /**
     * {@inheritDoc}
     */
    public function generateMapping(FieldType $current): array
    {
        $mapping = parent::generateMapping($current);

        return [
            $current->getName() => [
                    'type' => 'nested',
                    'properties' => [
                            EmsFields::CONTENT_MIME_TYPE_FIELD => $this->elasticsearchService->getKeywordMapping(),
                            EmsFields::CONTENT_FILE_HASH_FIELD => $this->elasticsearchService->getKeywordMapping(),
                            EmsFields::CONTENT_FILE_NAME_FIELD => $this->elasticsearchService->getIndexedStringMapping(),
                            EmsFields::CONTENT_FILE_SIZE_FIELD => $this->elasticsearchService->getLongMapping(),
                            '_content' => $mapping[$current->getName()],
                            '_author' => $mapping[$current->getName()],
                            '_title' => $mapping[$current->getName()],
                            '_date' => $this->elasticsearchService->getDateTimeMapping(),
                            '_language' => $this->elasticsearchService->getKeywordMapping(),
                    ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $dataField = parent::reverseViewTransform($data, $fieldType);
        $this->testDataField($dataField);

        return $dataField;
    }

    private function testDataField(DataField $dataField): void
    {
        $raw = $dataField->getRawData();
        if (!\is_array($raw) || empty($raw) || empty($raw['sha1'])) {
            $restrictionOptions = $dataField->giveFieldType()->getRestrictionOptions();
            if (isset($restrictionOptions['mandatory']) && $restrictionOptions['mandatory']) {
                $dataField->addMessage('This entry is required');
            }
            $dataField->setRawData(null);

            return;
        }
        if (!$this->fileService->head($raw['sha1'])) {
            $dataField->addMessage('File not found on the server try to re-upload it');

            return;
        }

        $raw['filesize'] = $this->fileService->getSize($raw['sha1']);
        if (isset($raw['_date'])) {
            $date = \strtotime((string) $raw['_date']);
            if (false !== $date) {
                $raw['_date'] = \date('c', $date);
            } else {
                $dataField->addMessage(\sprintf('Wrong date format for %s, use the ISO 8601 standard e.g. 1977-02-09T16:19:21+00:00', (string) $raw['_date']));
            }
        }
        $dataField->setRawData($raw);
    }

    /**
     * {@inheritDoc}
     */
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        if (\is_array($out) && empty($out['sha1'])) {
            $out = null;
        }

        return $out;
    }

    /**
     * {@inheritDoc}
     */
    public function modelTransform($data, FieldType $fieldType): DataField
    {
        if (\is_array($data)) {
            foreach ($data as $id => $content) {
                if (!\in_array($id, [EmsFields::CONTENT_FILE_HASH_FIELD, EmsFields::CONTENT_FILE_NAME_FIELD, EmsFields::CONTENT_FILE_SIZE_FIELD, EmsFields::CONTENT_MIME_TYPE_FIELD, EmsFields::CONTENT_FILE_DATE, EmsFields::CONTENT_FILE_AUTHOR, EmsFields::CONTENT_FILE_LANGUAGE, EmsFields::CONTENT_FILE_CONTENT, EmsFields::CONTENT_FILE_TITLE], true)) {
                    unset($data[$id]);
                } elseif ('sha1' !== $id && empty($data[$id])) {
                    unset($data[$id]);
                }
            }
        }

        return parent::reverseViewTransform($data, $fieldType);
    }
}
