<?php

namespace EMS\CoreBundle\Form\DataField;

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
    private FileService $fileService;

    /**
     * {@inheritDoc}
     */
    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, FileService $fileService)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
        $this->fileService = $fileService;
    }

    public static function getIcon(): string
    {
        return 'fa fa-file-o';
    }

    public function getLabel(): string
    {
        return 'File field';
    }

    public function getParent(): string
    {
        return AssetType::class;
    }

    /**
     * {@inheritDoc}
     */
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('multiple', false);
        $resolver->setDefault('imageAssetConfigIdentifier', null);
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['multiple'] = $options['multiple'];
    }

    /**
     * {@inheritDoc}
     */
    public function generateMapping(FieldType $current): array
    {
        return [
            $current->getName() => \array_merge([
                    'type' => 'nested',
                    'properties' => [
                        'mimetype' => $this->elasticsearchService->getKeywordMapping(),
                        'sha1' => $this->elasticsearchService->getKeywordMapping(),
                        'filename' => $this->elasticsearchService->getIndexedStringMapping(),
                        'filesize' => $this->elasticsearchService->getLongMapping(),
                    ],
            ], \array_filter($current->getMappingOptions())),
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
        $fieldType = $dataField->getFieldType();
        if (null === $fieldType || !$fieldType instanceof FieldType) {
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
        }

        if ($isMultiple) {
            $dataField->setRawData($rawData);
        } elseif (0 === \count($rawData)) {
            $dataField->setRawData(null);
        } else {
            $dataField->setRawData(\reset($rawData));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function viewTransform(DataField $dataField)
    {
        $fieldType = $dataField->getFieldType();
        if (null === $fieldType || !$fieldType instanceof FieldType) {
            throw new \RuntimeException('Unexpected fieldType type');
        }

        $out = parent::viewTransform($dataField);
        if (true !== $fieldType->getDisplayOption('multiple') && \is_array($out) && empty($out['sha1'])) {
            $out = null;
        }

        return $out;
    }

    /**
     * {@inheritDoc}
     */
    public function modelTransform($data, FieldType $fieldType): DataField
    {
        $out = parent::reverseViewTransform($data, $fieldType);
        if (true === $fieldType->getDisplayOption('multiple')) {
            $out->setRawData(['files' => $out->getRawData()]);
        }

        return $out;
    }
}
