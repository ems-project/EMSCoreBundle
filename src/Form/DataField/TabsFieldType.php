<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class TabsFieldType extends DataFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Visual tab container (invisible in Elasticsearch)';
    }

    /**
     * {@inheritdoc}
     */
    public function importData(DataField $dataField, $sourceArray, $isMigration)
    {
        throw new \Exception('This method should never be called');
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'tabsfieldtype';
    }

    /**
     * {@inheritdoc}
     */
    public static function getIcon()
    {
        return 'fa fa-object-group';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /* get the metadata associate */
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        /** @var FieldType $fieldType */
        foreach ($fieldType->getChildren() as $fieldType) {
            if (!$fieldType->getDeleted()) {
                /* merge the default options with the ones specified by the user */
                $options = \array_merge([
                        'metadata' => $fieldType,
                        'label' => false,
                        'migration' => $options['migration'],
                        'with_warning' => $options['with_warning'],
                        'raw_data' => $options['raw_data'],
                        'disabled_fields' => $options['disabled_fields'],
                ], $fieldType->getDisplayOptions());

                $builder->add($fieldType->getName(), $fieldType->getType(), $options);

                $builder->get($fieldType->getName())
                    ->addViewTransformer(new DataFieldViewTransformer($fieldType, $this->formRegistry))
                    ->addModelTransformer(new DataFieldModelTransformer($fieldType, $this->formRegistry));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildObjectArray(DataField $data, array &$out)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function isContainer()
    {
        /* this kind of compound field may contain children */
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        // tabs aren't mapped in elasticsearch
        $optionsForm->remove('mappingOptions');
        $optionsForm->remove('migrationOptions');
        $optionsForm->get('restrictionOptions')->remove('mandatory');
        $optionsForm->get('restrictionOptions')->remove('mandatory_if');
    }

    /**
     * {@inheritdoc}
     */
    public static function isVirtual(array $option = [])
    {
        return true;
    }

    /**
     * @return string[]
     */
    public static function getJsonNames(FieldType $current): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function generateMapping(FieldType $current)
    {
        return [];
    }
}
