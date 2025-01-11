<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SelectFieldType extends DataFieldType
{
    #[\Override]
    public function getLabel(): string
    {
        return 'Select field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-caret-square-o-down';
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $choices = [];
        $values = \explode("\n", \str_replace("\r", '', (string) $options['choices']));
        $labels = \explode("\n", \str_replace("\r", '', (string) $options['labels']));

        foreach ($values as $id => $value) {
            if (isset($labels[$id]) && \strlen($labels[$id]) > 0) {
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
            'empty_data' => $options['multiple'] ? [] : null,
            'multiple' => $options['multiple'],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('choices', []);
        $resolver->setDefault('labels', []);
        $resolver->setDefault('multiple', false);
    }

    #[\Override]
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            if ($data->giveFieldType()->getDisplayOptions()['multiple']) {
                $out[$data->giveFieldType()->getName()] = $data->getArrayTextValue();
            } else {
                parent::buildObjectArray($data, $out);
            }
        }
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific display options
        $optionsForm->get('displayOptions')
        ->add('multiple', CheckboxType::class, [
            'required' => false,
        ])->add('choices', TextareaType::class, [
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

        $out['mappingOptions']['index'] = 'not_analyzed';

        return $out;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'bypassdatafield';
    }

    /**
     * @param array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $value = null;
        if (isset($data['value'])) {
            $value = $data['value'];
        }

        return parent::reverseViewTransform($value, $fieldType);
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $temp = parent::viewTransform($dataField);
        if ($dataField->giveFieldType()->getDisplayOptions()['multiple']) {
            if (empty($temp)) {
                $out = [];
            } elseif (\is_string($temp)) {
                $out = [$temp];
            } elseif (\is_array($temp)) {
                $out = [];
                foreach ($temp as $item) {
                    if (\is_string($item)) {
                        $out[] = $item;
                    } else {
                        $dataField->addMessage(\sprintf('Was not able to import the data : %s', Json::encode($item)));
                    }
                }
            } else {
                $dataField->addMessage(\sprintf('Was not able to import the data : %s', Json::encode($temp)));
                $out = [];
            }
        } else { // not mutiple
            if (null === $temp) {
                $out = null;
            } elseif (\is_string($temp)) {
                $out = $temp;
            } elseif (\is_array($temp) && !empty($temp) && \is_string(\array_values($temp)[0])) {
                $out = \array_values($temp)[0];
                $dataField->addMessage(\sprintf('Only the first item has been imported : %s ', Json::encode($temp)));
            } else {
                $dataField->addMessage(\sprintf('Was not able to import the data : %s', Json::encode($temp)));

                return ['value' => ''];
            }
        }

        return ['value' => $out];
    }
}
