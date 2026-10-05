<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class EmailFieldType extends DataFieldType
{
    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-envelope';
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'bypassdatafield';
    }

    #[\Override]
    public function generateMcpSchema(FieldType $fieldType, callable $buildObjectSchema, bool $isOutputSchema = false): array
    {
        return ['type' => 'string', 'format' => 'email'];
    }

    #[\Override]
    public function getLabel(): TranslatableMessage
    {
        return t('field_type.email', [], 'emsco-core');
    }

    #[\Override]
    public function getDefaultOptions(string $name): array
    {
        $out = parent::getDefaultOptions($name);

        $out['mappingOptions']['analyzer'] = 'keyword';

        return $out;
    }

    #[\Override]
    public function isValid(DataField &$dataField, ?DataField $parent = null, mixed &$masterRawData = null): bool
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

    #[\Override]
    public function modelTransform($data, FieldType $fieldType): DataField
    {
        if (empty($data)) {
            return parent::modelTransform(null, $fieldType);
        }
        if (\is_string($data)) {
            return parent::modelTransform($data, $fieldType);
        }
        $out = parent::modelTransform(null, $fieldType);
        $out->addMessage('ems was not able to import the data: '.Json::encode($data));

        return $out;
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        return ['value' => parent::viewTransform($dataField)];
    }

    /**
     * @param array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        return parent::reverseViewTransform($data['value'], $fieldType);
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('value', TextType::class, [
            'label' => (null != $options['label'] ? $options['label'] : t('field.email', [], 'emsco-core')),
            'disabled' => $this->isDisabled($options),
            'required' => false,
        ]);
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
                ->add('analyzer', AnalyzerPickerType::class, [
                    'label' => (null != $options['label'] ? $options['label'] : t('field.analyzer', [], 'emsco-core')),
                ])
                ->add('copy_to', TextType::class, [
                    'label' => (null != $options['label'] ? $options['label'] : t('field.copy_to', [], 'emsco-core')),
                    'required' => false,
                ]);
        }
    }
}
