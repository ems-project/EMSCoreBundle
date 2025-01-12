<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AssetType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\FileService;
use EMS\Helpers\Standard\Type;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class AssetFieldType extends DataFieldType
{
    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, private readonly FileService $fileService)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-file-o';
    }

    /**
     * @param mixed[] $data
     */
    public static function loadFromDb(array &$data): void
    {
        foreach ([
            EmsFields::CONTENT_FILE_HASH_FIELD_ => EmsFields::CONTENT_FILE_HASH_FIELD,
            EmsFields::CONTENT_FILE_NAME_FIELD_ => EmsFields::CONTENT_FILE_NAME_FIELD,
            EmsFields::CONTENT_FILE_SIZE_FIELD_ => EmsFields::CONTENT_FILE_SIZE_FIELD,
            EmsFields::CONTENT_MIME_TYPE_FIELD_ => EmsFields::CONTENT_MIME_TYPE_FIELD,
        ] as $newField => $oldField) {
            if (!isset($data[$newField]) && isset($data[$oldField])) {
                $data[$newField] = $data[$oldField];
            }
            if (!isset($data[$newField])) {
                continue;
            }
            $data[$oldField] = $data[$newField];
        }
        foreach ($data as $id => $content) {
            if (!\in_array($id, [EmsFields::CONTENT_FILE_HASH_FIELD_, EmsFields::CONTENT_FILE_NAME_FIELD_, EmsFields::CONTENT_FILE_SIZE_FIELD_, EmsFields::CONTENT_MIME_TYPE_FIELD_,  EmsFields::CONTENT_FILE_HASH_FIELD, EmsFields::CONTENT_FILE_NAME_FIELD, EmsFields::CONTENT_FILE_SIZE_FIELD, EmsFields::CONTENT_MIME_TYPE_FIELD,  EmsFields::CONTENT_IMAGE_RESIZED_HASH_FIELD, EmsFields::CONTENT_FILE_DATE, EmsFields::CONTENT_FILE_AUTHOR, EmsFields::CONTENT_FILE_LANGUAGE, EmsFields::CONTENT_FILE_CONTENT, EmsFields::CONTENT_FILE_TITLE], true)) {
                unset($data[$id]);
            }
        }

        if (empty($data[EmsFields::CONTENT_FILE_HASH_FIELD_])) {
            unset($data[EmsFields::CONTENT_FILE_HASH_FIELD_]);
            unset($data[EmsFields::CONTENT_FILE_HASH_FIELD]);
        }
    }

    /**
     * @param mixed[] $data
     */
    public static function loadFromForm(array &$data, string $algo): void
    {
        $data[EmsFields::CONTENT_FILE_ALGO_FIELD_] ??= $algo;
        foreach ([
            EmsFields::CONTENT_FILE_HASH_FIELD_ => EmsFields::CONTENT_FILE_HASH_FIELD,
            EmsFields::CONTENT_FILE_NAME_FIELD_ => EmsFields::CONTENT_FILE_NAME_FIELD,
            EmsFields::CONTENT_FILE_SIZE_FIELD_ => EmsFields::CONTENT_FILE_SIZE_FIELD,
            EmsFields::CONTENT_MIME_TYPE_FIELD_ => EmsFields::CONTENT_MIME_TYPE_FIELD,
        ] as $newField => $oldField) {
            if (!isset($data[$oldField])) {
                continue;
            }
            $data[$newField] = $data[$oldField];
        }
        $data = \array_filter($data, fn ($value) => null !== $value);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'File field';
    }

    #[\Override]
    public function getParent(): string
    {
        return AssetType::class;
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        // container aren't mapped in elasticsearch
        $optionsForm->remove('mappingOptions');
        // an optional icon can't be specified ritgh to the container label
        $optionsForm->get('displayOptions')
        ->add('multiple', CheckboxType::class, [
            'required' => false,
        ])
        ->add('icon', IconPickerType::class, [
            'required' => false,
        ])
        ->add('imageAssetConfigIdentifier', TextType::class, [
            'required' => false,
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('multiple', false);
        $resolver->setDefault('imageAssetConfigIdentifier', null);
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['multiple'] = $options['multiple'];
    }

    #[\Override]
    public function generateMapping(FieldType $current): array
    {
        $mapping = parent::generateMapping($current);

        return [
            $current->getName() => \array_merge([
                'type' => 'nested',
                'properties' => [
                    EmsFields::CONTENT_MIME_TYPE_FIELD => $this->elasticsearchService->getKeywordMapping(),
                    EmsFields::CONTENT_MIME_TYPE_FIELD_ => $this->elasticsearchService->getKeywordMapping(),
                    EmsFields::CONTENT_FILE_HASH_FIELD => $this->elasticsearchService->getKeywordMapping(),
                    EmsFields::CONTENT_FILE_HASH_FIELD_ => $this->elasticsearchService->getKeywordMapping(),
                    EmsFields::CONTENT_FILE_NAME_FIELD => $this->elasticsearchService->getIndexedStringMapping(),
                    EmsFields::CONTENT_FILE_NAME_FIELD_ => $this->elasticsearchService->getIndexedStringMapping(),
                    EmsFields::CONTENT_FILE_SIZE_FIELD => $this->elasticsearchService->getLongMapping(),
                    EmsFields::CONTENT_FILE_SIZE_FIELD_ => $this->elasticsearchService->getLongMapping(),
                    EmsFields::CONTENT_IMAGE_RESIZED_HASH_FIELD => $this->elasticsearchService->getKeywordMapping(),
                    EmsFields::CONTENT_FILE_TITLE => $mapping[$current->getName()],
                ],
            ], \array_filter($current->getMappingOptions())),
        ];
    }

    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $multiple = true === $fieldType->getDisplayOption('multiple', false);
        if (\is_array($data) && $multiple) {
            foreach ($data as &$file) {
                if (!\is_array($file)) {
                    throw new \RuntimeException('Unexpected non array item');
                }
                self::loadFromForm($file, $this->fileService->getAlgo());
            }
        } elseif (\is_array($data)) {
            self::loadFromForm($data, $this->fileService->getAlgo());
        }
        $dataField = parent::reverseViewTransform($data, $fieldType);
        $this->testDataField($dataField);

        return $dataField;
    }

    private function testDataField(DataField $dataField): void
    {
        $fieldType = $dataField->getFieldType();
        if (null === $fieldType) {
            throw new \RuntimeException('Unexpected fieldType type');
        }

        $rawData = $dataField->getRawData();
        if (!\is_array($rawData)) {
            return;
        }

        $isMultiple = true === $fieldType->getDisplayOption('multiple', false);
        if ($isMultiple) {
            $data = $rawData['files'] ?? [];
        } else {
            $data = [$rawData];
        }

        $mandatory = (bool) $dataField->giveFieldType()->getRestrictionOption('mandatory', false);

        if (empty($data) && $mandatory) {
            $dataField->addMessage('This entry is required');
            $dataField->setRawData(null);
        }

        $rawData = [];
        foreach ($data as $fileInfo) {
            if (empty($fileInfo) || empty($fileInfo['sha1'])) {
                $restrictionOptions = $fieldType->getRestrictionOptions();
                if (isset($restrictionOptions['mandatory']) && $restrictionOptions['mandatory']) {
                    $dataField->addMessage('This entry is required');
                }
            } elseif (!$this->fileService->head($fileInfo['sha1'])) {
                $dataField->addMessage(\sprintf('File %s not found on the server try to re-upload it', $fileInfo['filename']));
            } else {
                $fileInfo['filesize'] = $this->fileService->getSize($fileInfo['sha1']);
                $rawData[] = $fileInfo;
            }
            if (!empty($fileInfo[EmsFields::CONTENT_IMAGE_RESIZED_HASH_FIELD]) && !$this->fileService->head($fileInfo[EmsFields::CONTENT_IMAGE_RESIZED_HASH_FIELD])) {
                $dataField->addMessage(\sprintf('Resized image of %s not found on the server try to re-upload the source image', $fileInfo['filename']));
            }
        }

        if ($isMultiple) {
            $dataField->setRawData($rawData);
        } elseif (0 === \count($rawData)) {
            $dataField->setRawData(null);
        } else {
            $dataField->setRawData(\reset($rawData));
        }
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $fieldType = $dataField->getFieldType();
        if (null === $fieldType) {
            throw new \RuntimeException('Unexpected fieldType type');
        }

        $out = parent::viewTransform($dataField);
        if (true !== $fieldType->getDisplayOption('multiple') && \is_array($out) && empty($out['sha1'])) {
            $out = null;
        }

        return $out;
    }

    #[\Override]
    public function modelTransform($data, FieldType $fieldType): DataField
    {
        $out = parent::reverseViewTransform($data, $fieldType);
        $data = $out->getRawData();
        if (!\is_array($data)) {
            $data = [];
        }
        $multiple = true === $fieldType->getDisplayOption('multiple');
        if ($multiple && (isset($data[EmsFields::CONTENT_FILE_HASH_FIELD]) || isset($data[EmsFields::CONTENT_FILE_HASH_FIELD_]))) {
            $data = [$data];
        }
        if (!$multiple && (isset($data[0][EmsFields::CONTENT_FILE_HASH_FIELD]) || isset($data[0][EmsFields::CONTENT_FILE_HASH_FIELD_]))) {
            if (\count($data) > 1) {
                $out->addMessage(\sprintf('An array of %d files has been converted into a single file field (with the first file)', \count($data)));
            }
            $data = $data[0];
        }
        if ($multiple) {
            foreach ($data as &$file) {
                self::loadFromDb($file);
            }
            $out->setRawData(['files' => $data]);
        } else {
            self::loadFromDb($data);
            $out->setRawData($data);
        }

        return $out;
    }
}
