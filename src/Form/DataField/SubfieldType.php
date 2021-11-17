<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

//TODO:Refact Class name "SubfieldType" to "SubfieldFieldType"
class SubfieldType extends DataFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Virtual subfield (used to define alternatives analyzers)';
    }

    /**
     * Get a icon to visually identify a FieldType.
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'fa fa-sitemap';
    }

    /**
     * {@inheritdoc}
     */
    public function importData(DataField $dataField, $sourceArray, $isMigration)
    {
        //do nothing as it's a virtual field
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        $optionsForm->remove('displayOptions')->remove('migrationOptions')->remove('restrictionOptions');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
                ->add('analyzer', AnalyzerPickerType::class)
                ->add('fielddata', CheckboxType::class, ['required' => false]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateMapping(FieldType $current)
    {
        $options = $this->elasticsearchService->updateMapping(\array_merge(['type' => 'string'], \array_filter($current->getMappingOptions())));

        return [
                'fields' => [$current->getName() => $options],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildObjectArray(DataField $data, array &$out)
    {
        //do nothing as it's a virtual field
    }
}
