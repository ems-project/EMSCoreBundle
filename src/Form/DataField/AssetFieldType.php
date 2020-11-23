<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AssetType;
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
        return [
            $current->getName() => array_merge([
                    'type' => 'nested',
                    'properties' => [
                            'mimetype' => $this->elasticsearchService->getKeywordMapping(),
                            'sha1' => $this->elasticsearchService->getKeywordMapping(),
                            'filename' => $this->elasticsearchService->getIndexedStringMapping(),
                            'filesize' => $this->elasticsearchService->getLongMapping(),
                    ],
            ], array_filter($current->getMappingOptions())),
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
            if ($dataField->getFieldType()->getRestrictionOptions()['mandatory']) {
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
        if (is_array($data)) {
            foreach ($data as $id => $content) {
                if (!in_array($id, ['sha1', 'filename', 'mimetype'], true)) {
                    unset($data[$id]);
                }
            }
        }

        return parent::reverseViewTransform($data, $fieldType);
    }

//     public function convertInput(DataField $dataField) {
//         if(!empty($dataField->getInputValue()) && !empty($dataField->getInputValue()['sha1'])){
//             $rawData = $dataField->getInputValue();
//             $rawData['filesize'] = $this->fileService->getSize($rawData['sha1']);
//             if(!$rawData['filesize']){
//                 unset($rawData['filesize']);
//             }

//             $dataField->setRawData($rawData);
//         }
//         else{
//             $dataField->setRawData(null);
//         }
//     }

//     public function generateInput(DataField $dataField){
//         $rawData = $dataField->getRawData();

//         if(!empty($rawData) && !empty($rawData['sha1'])){
//             unset($rawData['filesize']);
//             $dataField->setInputValue($rawData);
//         }
//         else {
//             $dataField->setInputValue(null);
//         }
//         return $this;
//     }
}
