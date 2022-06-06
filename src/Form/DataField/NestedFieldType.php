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
 * Defined a Nested obecjt.
 * It's used to  groups subfields together.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class NestedFieldType extends DataFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Nested object';
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
    public function importData(DataField $dataField, $sourceArray, $isMigration)
    {
        $migrationOptions = $dataField->getFieldType()->getMigrationOptions();
        if (!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
            foreach ($dataField->getChildren() as $child) {
                $child->updateDataValue($sourceArray);
            }
        }

        return [$dataField->getFieldType()->getName()];
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
                        'migration' => $options['migration'],
                        'with_warning' => $options['with_warning'],
                        'label' => false,
                        'disabled_fields' => $options['disabled_fields'],
                        'referrer-ems-id' => $options['referrer-ems-id'],
                ], $fieldType->getDisplayOptions());
                $builder->add($fieldType->getName(), $fieldType->getType(), $options);

                $builder->get($fieldType->getName())
                    ->addViewTransformer(new DataFieldViewTransformer($fieldType, $this->formRegistry))
                    ->addModelTransformer(new DataFieldModelTransformer($fieldType, $this->formRegistry));
            }
        }
    }

    public function getBlockPrefix()
    {
        return 'container_field_type';
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        /* give options for twig context */
        parent::buildView($view, $form, $options);
        $view->vars['icon'] = $options['icon'];
        $view->vars['multiple'] = $options['multiple'];
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
        $resolver->setDefault('multiple', false);
    }

    /**
     * {@inheritdoc}
     */
    public function buildObjectArray(DataField $data, array &$out)
    {
        if (null == $data->getFieldType()) {
            $tmp = [];
            /** @var DataField $child */
            foreach ($data->getChildren() as $child) {
//                 $className = $child->getFieldType()->getType();
//                 $class = new $className;
                $class = $this->formRegistry->getType($child->getFieldType()->getType());
                $class->buildObjectArray($child, $tmp);
            }
            $out[] = $tmp;
        } elseif (!$data->getFieldType()->getDeleted()) {
            $out[$data->getFieldType()->getName()] = [];
        }
    }

    public static function isNested()
    {
        return true;
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
        // nested doesn't not have that much options in elasticsearch
        $optionsForm->remove('mappingOptions');
        // an optional icon can't be specified ritgh to the container label
        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
                'required' => false,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function generateMapping(FieldType $current)
    {
        return [
            $current->getName() => [
                'type' => 'nested',
                'properties' => [],
            ], ];
    }
}
