<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RadioFieldType extends DataFieldType
{
    #[\Override]
    public function getLabel(): string
    {
        return 'Radio field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-dot-circle-o';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $choices = [];
        $values = \explode("\n", \str_replace("\r", '', (string) $options['choices']));
        $labels = \explode("\n", \str_replace("\r", '', (string) $options['labels']));

        foreach ($values as $id => $value) {
            if (isset($labels[$id]) && !empty($labels[$id])) {
                $choices[$labels[$id]] = $value;
            } else {
                $choices[$value] = $value;
            }
        }

        $builder->add('value', ChoiceType::class, [
            'label' => ($options['label'] ?? $fieldType->getName()),
            'required' => false,
            'disabled' => $this->isDisabled($options),
            'choices' => $choices,
            'empty_data' => null,
            'multiple' => false,
            'expanded' => true,
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('choices', []);
        $resolver->setDefault('labels', []);
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific display options
        $optionsForm->get('displayOptions')->add('choices', TextareaType::class, [
            'required' => false,
        ])->add('labels', TextareaType::class, [
            'required' => false,
        ]);

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('analyzer', AnalyzerPickerType::class);
        }
    }

    #[\Override]
    public function getDefaultOptions(string $name): array
    {
        $out = parent::getDefaultOptions($name);

        $out['mappingOptions']['analyzer'] = 'keyword';

        return $out;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'bypassdatafield';
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        return ['value' => $out];
    }

    /**
     * @param array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $value = $data['value'];

        return parent::reverseViewTransform($value, $fieldType);
    }
}
