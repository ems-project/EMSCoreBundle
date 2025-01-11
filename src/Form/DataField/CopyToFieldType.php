<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class CopyToFieldType extends DataFieldType
{
    #[\Override]
    public function getLabel(): string
    {
        return 'Elasticsearch copy_to field';
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'empty';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-copy';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // no inputs as it's just an indexing field
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('analyzer', AnalyzerPickerType::class);
            $optionsForm->get('mappingOptions')->add('store', CheckboxType::class, [
                'required' => false,
            ]);
        }
        $optionsForm->remove('restrictionOptions');
        $optionsForm->remove('migrationOptions');
        $optionsForm->remove('extraOptions');
        $optionsForm->remove('displayOptions');
    }

    #[\Override]
    public function buildObjectArray(DataField $data, array &$out): void
    {
        // do nothing more than a mapping
    }
}
