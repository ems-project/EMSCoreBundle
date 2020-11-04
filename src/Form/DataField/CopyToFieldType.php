<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\FormBuilderInterface;
use EMS\CoreBundle\Entity\DataField;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
                    
/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class CopyToFieldType extends DataFieldType
{
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getLabel()
    {
        return 'Elasticsearch copy_to field';
    }
    
    
    /**
     *
     * {@inheritDoc}
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::getBlockPrefix()
     */
    public function getBlockPrefix()
    {
        return 'empty';
    }
    
    /**
     * Get a icon to visually identify a FieldType
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'fa fa-copy';
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        //no inputs as it's just an indexing field
    }
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        
        // String specific mapping options
        $optionsForm->get('mappingOptions')->add('analyzer', AnalyzerPickerType::class);
        $optionsForm->get('mappingOptions')->add('store', CheckboxType::class, [
                'required' => false,
        ]);
        $optionsForm->remove('restrictionOptions');
        $optionsForm->remove('migrationOptions');
        $optionsForm->remove('extraOptions');
        $optionsForm->remove('displayOptions');
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildObjectArray(DataField $data, array &$out)
    {
        //do nothing more than a mapping
    }
}
