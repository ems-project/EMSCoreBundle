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
    public function getLabel(): string
    {
        return 'Nested object';
    }

    public static function getIcon(): string
    {
        return 'glyphicon glyphicon-modal-window';
    }

    /**
     * {@inheritDoc}
     */
    public function importData(DataField $dataField, array|string|int|float|bool|null $sourceArray, bool $isMigration): array
    {
        $migrationOptions = $dataField->giveFieldType()->getMigrationOptions();
        if (!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
            foreach ($dataField->getChildren() as $child) {
                $child->updateDataValue($sourceArray);
            }
        }

        return [$dataField->giveFieldType()->getName()];
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
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

    public function getBlockPrefix(): string
    {
        return 'container_field_type';
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /* give options for twig context */
        parent::buildView($view, $form, $options);
        $view->vars['icon'] = $options['icon'];
        $view->vars['multiple'] = $options['multiple'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        /* an optional icon can't be specified ritgh to the container label */
        $resolver->setDefault('icon', null);
        $resolver->setDefault('multiple', false);
    }

    /**
     * {@inheritDoc}
     */
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (null == $data->giveFieldType()) {
            $tmp = [];
            /** @var DataField $child */
            foreach ($data->getChildren() as $child) {
//                 $className = $child->getFieldType()->getType();
//                 $class = new $className;
                $class = $this->formRegistry->getType($child->giveFieldType()->getType());

                if (\method_exists($class, 'buildObjectArray')) {
                    $class->buildObjectArray($child, $tmp);
                }
            }
            $out[] = $tmp;
        } elseif (!$data->giveFieldType()->getDeleted()) {
            $out[$data->giveFieldType()->getName()] = [];
        }
    }

    public static function isNested(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public static function isContainer(): bool
    {
        /* this kind of compound field may contain children */
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
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
     * {@inheritDoc}
     */
    public function generateMapping(FieldType $current): array
    {
        return [
            $current->getName() => [
                'type' => 'nested',
                'properties' => [],
            ], ];
    }
}
