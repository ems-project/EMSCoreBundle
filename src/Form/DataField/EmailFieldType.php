<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class EmailFieldType extends DataFieldType
{
    public static function getIcon(): string
    {
        return 'fa fa-envelope';
    }

    public function getBlockPrefix(): string
    {
        return 'bypassdatafield';
    }

    public function getLabel(): string
    {
        return 'Email field';
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultOptions(string $name): array
    {
        $out = parent::getDefaultOptions($name);

        $out['mappingOptions']['index'] = 'not_analyzed';

        return $out;
    }

    /**
     * {@inheritDoc}
     */
    public function isValid(DataField &$dataField, DataField $parent = null, &$masterRawData = null): bool
    {
        if ($this->hasDeletedParent($parent)) {
            return true;
        }

        $isValid = parent::isValid($dataField, $parent, $masterRawData);

        $rawData = $dataField->getRawData();
        if (!empty($rawData) && false === \filter_var($rawData, FILTER_VALIDATE_EMAIL)) {
            $isValid = false;
            $dataField->addMessage('Not a valid email address');
        }

        return $isValid;
    }

    /**
     * {@inheritDoc}
     */
    public function modelTransform($data, FieldType $fieldType): DataField
    {
        if (empty($data)) {
            return parent::modelTransform(null, $fieldType);
        }
        if (\is_string($data)) {
            return parent::modelTransform($data, $fieldType);
        }
        $out = parent::modelTransform(null, $fieldType);
        $out->addMessage('ems was not able to import the data: '.\json_encode($data, JSON_THROW_ON_ERROR));

        return $out;
    }

    /**
     * {@inheritDoc}
     */
    public function viewTransform(DataField $dataField)
    {
        return ['value' => parent::viewTransform($dataField)];
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        return parent::reverseViewTransform($data['value'], $fieldType);
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['metadata'];
        $builder->add('value', TextType::class, [
                'label' => (null != $options['label'] ? $options['label'] : 'Email field type'),
                'disabled' => $this->isDisabled($options),
                'required' => false,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
                ->add('analyzer', AnalyzerPickerType::class)
                ->add('copy_to', TextType::class, ['required' => false]);
        }
    }
}
