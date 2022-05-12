<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use EMS\CoreBundle\Form\Field\IconPickerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class ContainerFieldType extends DataFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Visual container (invisible in Elasticsearch)';
    }

    public function getBlockPrefix()
    {
        return 'container_field_type';
    }

    /**
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::postFinalizeTreatment()
     */
    public function postFinalizeTreatment($type, $id, DataField $dataField, $previousData)
    {
        if (!empty($previousData[$dataField->getFieldType()->getName()])) {
            return $previousData[$dataField->getFieldType()->getName()];
        }

        return null;
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
    public static function getIcon()
    {
        return 'glyphicon glyphicon-modal-window';
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
        foreach ($fieldType->getChildren() as $child) {
            if (!$child->getDeleted()) {
                /* merge the default options with the ones specified by the user */
                $options = \array_merge([
                        'metadata' => $child,
                        'label' => false,
                        'migration' => $options['migration'],
                        'with_warning' => $options['with_warning'],
                        'raw_data' => $options['raw_data'],
                        'disabled_fields' => $options['disabled_fields'],
                        'referrer-ems-id' => $options['referrer-ems-id'],
                ], $child->getDisplayOptions());

                $builder->add($child->getName(), $child->getType(), $options);

                $builder->get($child->getName())
                    ->addViewTransformer(new DataFieldViewTransformer($child, $this->formRegistry))
                    ->addModelTransformer(new DataFieldModelTransformer($child, $this->formRegistry));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        /* give options for twig context */
        parent::buildView($view, $form, $options);
        $view->vars['icon'] = $options['icon'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        /* an optional icon can't be specified ritgh to the container label */
        $resolver->setDefault('icon', null);
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
        // container aren't mapped in elasticsearch
        $optionsForm->remove('mappingOptions');
        $optionsForm->remove('migrationOptions');
        $optionsForm->get('restrictionOptions')->remove('mandatory');
        $optionsForm->get('restrictionOptions')->remove('mandatory_if');
        // an optional icon can't be specified ritgh to the container label
        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
                'required' => false,
        ]);
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
