<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AssetType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\FileService;
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
        return 'fa fa-file-o';
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'File field';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return AssetType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
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

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
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
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['multiple'] = $options['multiple'];
    }

    /**
     * {@inheritdoc}
     */
    public function generateMapping(FieldType $current)
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

        if (empty($data) && $dataField->getFieldType()->getRestrictionOptions()['mandatory'] ?? false) {
            $dataField->addMessage('This entry is required');
            $dataField->setRawData(null);
        }

        $rawData = [];
        foreach ($data as $fileInfo) {
            if ((empty($fileInfo) || empty($fileInfo['sha1']))) {
                if (isset($fieldType->getRestrictionOptions()['mandatory']) && $fieldType->getRestrictionOptions()['mandatory']) {
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
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
     */
    public function viewTransform(DataField $dataField)
    {
        $fieldType = $dataField->getFieldType();
        if (null === $fieldType || !$fieldType instanceof FieldType) {
            throw new \RuntimeException('Unexpected fieldType type');
        }

        $out = parent::viewTransform($dataField);
        if (true !== $fieldType->getDisplayOption('multiple') && empty($out['sha1'])) {
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
        $out = parent::reverseViewTransform($data, $fieldType);
        if (true === $fieldType->getDisplayOption('multiple')) {
            $out->setRawData(['files' => $out->getRawData()]);
        }

        return $out;
    }
}
