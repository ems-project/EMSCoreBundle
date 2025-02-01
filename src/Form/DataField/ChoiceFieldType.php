<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChoiceFieldType extends DataFieldType
{
    private ?int $fakeIndex = null;

    #[\Override]
    public function getLabel(): string
    {
        return 'Choice field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'glyphicon glyphicon-check';
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

    /**
     * @return array<string, string>
     */
    public function choiceAttr(mixed $choiceValue, mixed $key, mixed $value): array
    {
        $out = [];
        if (null !== $this->fakeIndex && \is_int($choiceValue) && $choiceValue >= $this->fakeIndex) {
            $out['class'] = 'input-to-hide';
        }

        return $out;
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];
        $choices = $this->buildChoices($options);

        $builder->add('value', ChoiceType::class, [
            'label' => ($options['label'] ?? $fieldType->getName()),
            'required' => false,
            'disabled' => $this->isDisabled($options),
            'choices' => $choices,
            'empty_data' => $options['multiple'] ? [] : null,
            'multiple' => $options['multiple'],
            'expanded' => $options['expanded'],
            'placeholder' => $options['placeholder'] ?? '',
            'choice_attr' => $this->choiceAttr(...),
        ]);
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['attr'] = [
            'disabled' => $this->isDisabled($options),
            'data-linked-collection' => $options['linked_collection'],
            'data-collection-label-field' => $options['collection_label_field'],
            'data-multiple' => $options['multiple'],
            'data-expanded' => $options['expanded'],
            'class' => 'ems-choice-field-type'.($options['select2'] ? ' select2' : ''),
        ];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('choices', []);
        $resolver->setDefault('labels', []);
        $resolver->setDefault('multiple', false);
        $resolver->setDefault('expanded', false);
        $resolver->setDefault('select2', false);
        $resolver->setDefault('linked_collection', false);
        $resolver->setDefault('collection_label_field', false);
        $resolver->setDefault('placeholder', null);
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific display options
        $optionsForm->get('displayOptions')->add('multiple', CheckboxType::class, [
            'required' => false,
        ])->add('expanded', CheckboxType::class, [
            'required' => false,
        ])->add('select2', CheckboxType::class, [
            'required' => false,
        ])->add('choices', TextareaType::class, [
            'required' => false,
        ])->add('labels', TextareaType::class, [
            'required' => false,
        ])->add('placeholder', TextType::class, [
            'required' => false,
        ])->add('linked_collection', TextType::class, [
            'required' => false,
        ])->add('collection_label_field', TextType::class, [
            'required' => false,
        ]);

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
                ->add('analyzer', AnalyzerPickerType::class)
                ->add('copy_to', TextType::class, [
                    'required' => false,
                ]);
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
        return 'ems_choice';
    }

    /**
     * @param array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $value = $data['value'] ?? null;
        if (\is_array($value)) {
            $choices = $this->buildChoices($fieldType->getDisplayOptions());
            $value = \array_values(\array_filter($value, static fn ($v) => \in_array($v, $choices, true)));
        }

        return parent::reverseViewTransform($value, $fieldType);
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $temp = parent::viewTransform($dataField);
        $out = [];
        if ($dataField->giveFieldType()->getDisplayOptions()['multiple']) {
            if (empty($temp)) {
                $out = [];
            } elseif (\is_string($temp)) {
                $out = [$temp];
            } elseif (\is_array($temp)) {
                $out = [];
                foreach ($temp as $item) {
                    if (\is_string($item) || \is_int($item)) {
                        $out[] = $item;
                    } else {
                        $dataField->addMessage('Was not able to import the data : '.Json::encode($item));
                    }
                }
            } else {
                $dataField->addMessage('Was not able to import the data : '.Json::encode($out));
                $out = [];
            }
        } else { // not mutiple
            if (null === $temp) {
                $out = null;
            } elseif (\is_string($temp) || \is_int($temp)) {
                $out = $temp;
            } elseif (\is_array($temp) && null != $temp && (\is_string(\array_values($temp)[0]) || \is_int(\array_values($temp)[0]))) {
                $out = \array_values($temp)[0];
                $dataField->addMessage('Only the first item has been imported : '.Json::encode($temp));
            } else {
                $dataField->addMessage('Was not able to import the data : '.Json::encode($temp));
                $out = [];
            }
        }

        return ['value' => $out];
    }

    /**
     * @param  array<string, mixed>      $options
     * @return array<string, string|int>
     */
    private function buildChoices(array $options): array
    {
        $choices = [];
        $values = \explode("\n", \str_replace("\r", '', (string) $options['choices']));
        $labels = \explode("\n", \str_replace("\r", '', (string) $options['labels']));

        foreach ($values as $id => $value) {
            if ('' != $value) {
                if (isset($labels[$id]) && !empty($labels[$id])) {
                    $choices[$labels[$id]] = $value;
                } else {
                    $choices[$value] = $value;
                }
            }
        }

        if (\is_string($options['linked_collection'] ?? null)) {
            $idx = 0;
            if (\is_array($options['raw_data'][$options['linked_collection']] ?? null)) {
                foreach ($options['raw_data'][$options['linked_collection']] as $idx => $child) {
                    $choices['#'.$idx.': '.((null !== ($child[$options['collection_label_field'] ?? 'label'] ?? null)) ? $child[$options['collection_label_field']] : '')] = $idx;
                }
                ++$idx;
            }

            $this->fakeIndex = $idx;

            for ($i = 0; $i < 50; ++$i) {
                $choices['[ems_hide_input]'.($idx + $i)] = $idx + $i;
            }
        }

        return $choices;
    }
}
