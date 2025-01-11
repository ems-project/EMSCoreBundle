<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

// TODO:Refact Class name "SubfieldType" to "SubfieldFieldType"
class SubfieldType extends DataFieldType
{
    #[\Override]
    public function getLabel(): string
    {
        return 'Virtual subfield (used to define alternatives analyzers)';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-sitemap';
    }

    #[\Override]
    public function importData(DataField $dataField, array|string|int|float|bool|null $sourceArray, bool $isMigration): array
    {
        return [];
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
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

    #[\Override]
    public function generateMapping(FieldType $current): array
    {
        $options = $this->elasticsearchService->updateMapping(\array_merge(['type' => 'string'], \array_filter($current->getMappingOptions())));

        return [
            'fields' => [$current->getName() => $options],
        ];
    }

    #[\Override]
    public function buildObjectArray(DataField $data, array &$out): void
    {
        // do nothing as it's a virtual field
    }
}
