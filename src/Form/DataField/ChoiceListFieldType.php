<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\ChoiceList;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Repository\ChoiceListRepository;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

class ChoiceListFieldType extends DataFieldType
{
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        TokenStorageInterface $tokenStorage,
        private readonly ChoiceListRepository $choiceListRepository,
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService, $tokenStorage);
    }

    #[\Override]
    public function generateMcpSchema(FieldType $fieldType, callable $buildObjectSchema, bool $isOutputSchema = false): array
    {
        $values = \array_values(\array_map(
            static fn (mixed $choice): string => (string) ($choice['value'] ?? ''),
            $this->getChoiceDefinitions($fieldType),
        ));
        $schema = ['type' => 'string'];
        if ([] !== $values) {
            $schema['enum'] = $values;
        }

        return (bool) $fieldType->getDisplayOption('multiple', false)
            ? ['type' => 'array', 'items' => $schema]
            : $schema;
    }

    #[\Override]
    public function getLabel(): TranslatableMessage
    {
        return t('field_type.choice_list', [], 'emsco-core');
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-list';
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['metadata'];
        $choiceListName = $fieldType->getDisplayOption('choice_list', '');
        $choiceList = \is_string($choiceListName) && '' !== $choiceListName
            ? $this->choiceListRepository->getByName($choiceListName)
            : null;

        $builder->add('value', ChoiceType::class, [
            'label' => $options['label'] ?? $fieldType->getName(),
            'required' => false,
            'disabled' => $this->isDisabled($options),
            'choices' => $this->buildChoices($choiceList, (string) ($options['locale'] ?? '')),
            'empty_data' => $options['multiple'] ? [] : null,
            'multiple' => $options['multiple'],
            'expanded' => $options['expanded'],
            'placeholder' => $options['placeholder'] ?? '',
            'attr' => [
                'class' => $options['select2'] ? 'select2' : '',
            ],
        ]);
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        $choices = [];
        foreach ($this->choiceListRepository->getAll() as $choiceList) {
            $choices[$choiceList->getLabel()] = $choiceList->getName();
        }

        $optionsForm->get('displayOptions')
            ->add('choice_list', ChoiceType::class, [
                'choices' => $choices,
                'required' => true,
                'choice_translation_domain' => false,
            ])
            ->add('multiple', CheckboxType::class, ['required' => false])
            ->add('expanded', CheckboxType::class, ['required' => false])
            ->add('select2', CheckboxType::class, ['required' => false])
            ->add('placeholder', TextType::class, ['required' => false]);

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
                ->add('analyzer', AnalyzerPickerType::class)
                ->add('copy_to', TextType::class, ['required' => false]);
        }
    }

    #[\Override]
    public function getDefaultOptions(string $name): array
    {
        $options = parent::getDefaultOptions($name);
        $options['mappingOptions']['analyzer'] = 'keyword';

        return $options;
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
        $allowed = \array_column($this->getChoiceDefinitions($fieldType), 'value');
        if (\is_array($value)) {
            $value = \array_values(\array_filter($value, static fn (mixed $item): bool => \in_array($item, $allowed, true)));
        } elseif (null !== $value && !\in_array($value, $allowed, true)) {
            $value = null;
        }

        return parent::reverseViewTransform($value, $fieldType);
    }

    public function viewTransform(DataField $dataField): mixed
    {
        $value = parent::viewTransform($dataField);
        if (!(bool) $dataField->giveFieldType()->getDisplayOption('multiple', false)) {
            return ['value' => $value];
        }

        if (null === $value || '' === $value) {
            return ['value' => []];
        }
        if (\is_string($value) || \is_int($value)) {
            return ['value' => [(string) $value]];
        }
        if (\is_array($value)) {
            return ['value' => \array_values(\array_filter($value, static fn (mixed $item): bool => \is_string($item) || \is_int($item)))];
        }

        $dataField->addMessage('Was not able to import data: '.Json::encode($value));

        return ['value' => []];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('choice_list', null);
        $resolver->setDefault('multiple', false);
        $resolver->setDefault('expanded', false);
        $resolver->setDefault('select2', false);
        $resolver->setDefault('placeholder', null);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildChoices(?ChoiceList $choiceList, string $locale): array
    {
        if (null === $choiceList) {
            return [];
        }

        $options = $choiceList->getOptions();
        $sortOrder = \is_string($options['sortOrder'] ?? null) ? $options['sortOrder'] : null;
        $groups = \is_array($options['groups'] ?? null) ? $options['groups'] : [];
        $choices = \is_array($options['choices'] ?? null) ? $options['choices'] : [];
        $groupLabels = [];
        foreach ($groups as $group) {
            if (!\is_array($group) || !\is_string($group['name'] ?? null)) {
                continue;
            }
            $groupLabels[$group['name']] = $this->localizedLabel($group['labels'] ?? [], $locale, (string) ($group['label'] ?? $group['name']));
        }

        /** @var array<string, string|array<string, string>> $result */
        $result = [];
        foreach ($choices as $choice) {
            if (!\is_array($choice) || !isset($choice['value'])) {
                continue;
            }
            $value = (string) $choice['value'];
            $label = $this->localizedLabel($choice['labels'] ?? [], $locale, (string) ($choice['label'] ?? $value));
            $group = \is_string($choice['group'] ?? null) ? $choice['group'] : null;
            if (null !== $group && isset($groupLabels[$group])) {
                $groupLabel = $groupLabels[$group];
                $groupChoices = \is_array($result[$groupLabel] ?? null) ? $result[$groupLabel] : [];
                $groupChoices[$label] = $value;
                $result[$groupLabel] = $groupChoices;
            } else {
                $result[$label] = $value;
            }
        }

        if (\in_array($sortOrder, ['asc', 'desc'], true)) {
            $result = self::sortChoices($result, $sortOrder);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed> $choices
     * @return array<string, mixed>
     */
    private static function sortChoices(array $choices, string $sortOrder): array
    {
        foreach ($choices as $key => $choice) {
            if (\is_array($choice)) {
                $choices[$key] = self::sortChoices($choice, $sortOrder);
            }
        }

        \uksort($choices, static fn (string $left, string $right): int => 'asc' === $sortOrder
            ? \strnatcasecmp($left, $right)
            : \strnatcasecmp($right, $left));

        return $choices;
    }

    /**
     * @return array<int, mixed>
     */
    private function getChoiceDefinitions(FieldType $fieldType): array
    {
        $name = $fieldType->getDisplayOption('choice_list', '');
        if (!\is_string($name) || '' === $name) {
            return [];
        }

        $choiceList = $this->choiceListRepository->getByName($name);
        if (null === $choiceList) {
            return [];
        }
        $choices = $choiceList->getOptions()['choices'] ?? [];

        return \is_array($choices) ? $choices : [];
    }

    private function localizedLabel(mixed $labels, string $locale, string $fallback): string
    {
        if (!\is_array($labels)) {
            return $fallback;
        }
        if (isset($labels[$locale]) && \is_string($labels[$locale]) && '' !== $labels[$locale]) {
            return $labels[$locale];
        }
        foreach ($labels as $label) {
            if (\is_string($label) && '' !== $label) {
                return $label;
            }
        }

        return $fallback;
    }
}
