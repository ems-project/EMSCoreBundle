<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MultiplexedTabContainerFieldType extends DataFieldType
{
    private const string LOCALE_PREFERRED_FIRST_DISPLAY_OPTION = 'localePreferredFirst';
    private const string WITH_LOCALES_VARIABLE_DISPLAY_OPTION = 'withLocalesVariable';
    private const string LABELS_DISPLAY_OPTION = 'labels';
    private const string VALUES_DISPLAY_OPTION = 'values';
    private const string ICON_DISPLAY_OPTION = 'icon';

    /**
     * @param string[] $locales
     */
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        private readonly UserManager $userManager,
        private readonly array $locales
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
    }

    #[\Override]
    public function generateMcpSchema(FieldType $fieldType, callable $buildObjectSchema, bool $isOutputSchema = false): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false,
        ];
        $childSchema = $buildObjectSchema($fieldType->getValidChildren());

        foreach ($this->buildSchemaChoices($fieldType) as $label => $value) {
            $schema['properties'][$value] = [...$childSchema, 'title' => $label];
        }

        if ([] === $schema['properties']) {
            $schema['properties'] = new \stdClass();
        }

        return $schema;
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Multiplexed Tab Container';
    }

    #[\Override]
    public static function isContainer(): bool
    {
        return true;
    }

    #[\Override]
    public static function isNested(): bool
    {
        return true;
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('displayOptions')->add(self::VALUES_DISPLAY_OPTION, TextareaType::class, [
            'required' => false,
        ])->add(self::LABELS_DISPLAY_OPTION, TextareaType::class, [
            'required' => false,
        ])
        ->add(self::LOCALE_PREFERRED_FIRST_DISPLAY_OPTION, CheckboxType::class, [
            'required' => false,
        ])
        ->add(self::WITH_LOCALES_VARIABLE_DISPLAY_OPTION, CheckboxType::class, [
            'required' => false,
        ])
        ->add(self::ICON_DISPLAY_OPTION, IconPickerType::class, [
            'required' => false,
        ]);

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->remove('mappingOptions');
        }

        if ($optionsForm->has('restrictionOptions')) {
            $optionsForm->remove('restrictionOptions');
        }

        if ($optionsForm->has('migrationOptions')) {
            $optionsForm->remove('migrationOptions');
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault(self::VALUES_DISPLAY_OPTION, '');
        $resolver->setDefault(self::LABELS_DISPLAY_OPTION, '');
        $resolver->setDefault(self::LOCALE_PREFERRED_FIRST_DISPLAY_OPTION, false);
        $resolver->setDefault(self::WITH_LOCALES_VARIABLE_DISPLAY_OPTION, false);
        $resolver->setDefault(self::ICON_DISPLAY_OPTION, null);
    }

    /** @return string[] */
    private static function getLocaleValues(FieldType $fieldType): array
    {
        if ($fieldType->getDisplayOption(self::WITH_LOCALES_VARIABLE_DISPLAY_OPTION, false)) {
            return Json::decode($_ENV['EMSCH_LOCALES'] ?? $_SERVER['EMSCH_LOCALES'] ?? '[]');
        }

        if ($values = $fieldType->getDisplayOption(self::VALUES_DISPLAY_OPTION)) {
            return self::textAreaToArray($values);
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function buildSchemaChoices(FieldType $fieldType): array
    {
        $choices = [];

        if (true === $fieldType->getDisplayOption(self::WITH_LOCALES_VARIABLE_DISPLAY_OPTION, false)) {
            foreach ($this->locales as $locale) {
                $choices[$locale] = Locales::getName($locale);
            }
        }

        $values = self::textAreaToArray($fieldType->getDisplayOption(self::VALUES_DISPLAY_OPTION));
        $labels = self::textAreaToArray($fieldType->getDisplayOption(self::LABELS_DISPLAY_OPTION));

        foreach ($values as $index => $value) {
            if ('' !== $value) {
                $choices[$value] = $labels[$index] ?? $value;
            }
        }

        return \array_flip($choices);
    }

    #[\Override]
    public function generateMapping(FieldType $current): array
    {
        $mapping = [];
        $values = self::getLocaleValues($current);

        foreach ($values as $value) {
            $mapping[$value] = ['properties' => []];
        }

        return $mapping;
    }

    #[\Override]
    public static function getJsonNames(FieldType $current): array
    {
        return self::getLocaleValues($current);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'tabsfieldtype';
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldType = $builder->getOptions()['metadata'];
        if (!$fieldType instanceof FieldType) {
            throw new \RuntimeException('Unexpected FieldType type');
        }
        $choices = $this->getChoices($fieldType, $options['locale']);
        foreach ($choices as $label => $value) {
            $builder->add($value, ContainerFieldType::class, [
                'metadata' => $fieldType,
                'label' => $label,
                'migration' => $options['migration'],
                'icon' => $options[self::ICON_DISPLAY_OPTION] ?? null,
                'with_warning' => $options['with_warning'],
                'raw_data' => $options['raw_data'],
                'disabled_fields' => $options['disabled_fields'],
                'referrer-ems-id' => $options['referrer-ems-id'],
                'locale' => $value,
            ]);

            $builder->get($value)
                ->addViewTransformer(new DataFieldViewTransformer($fieldType, $this->formRegistry))
                ->addModelTransformer(new DataFieldModelTransformer($fieldType, $this->formRegistry));
        }
    }

    #[\Override]
    public function importData(DataField $dataField, float|int|bool|array|string|null $sourceArray, bool $isMigration): array
    {
        parent::importData($dataField, $sourceArray, $isMigration);

        return self::getJsonNames($dataField->giveFieldType());
    }

    #[\Override]
    public static function isVirtual(array $option = []): bool
    {
        return true;
    }

    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                if (null === $value) {
                    unset($data[$key]);
                }
            }
        }

        return parent::reverseViewTransform($data, $fieldType);
    }

    /**
     * @return array<string, string>
     */
    private function getChoices(FieldType $fieldType, ?string $locale = null): array
    {
        $choices = [];

        $withLocalesVariable = true === $fieldType->getDisplayOption(self::WITH_LOCALES_VARIABLE_DISPLAY_OPTION, false);
        if ($withLocalesVariable) {
            foreach ($this->locales as $varLocale) {
                $choices[$varLocale] = Locales::getName($varLocale);
            }
        }

        $values = $fieldType->getDisplayOption(self::VALUES_DISPLAY_OPTION);
        if (null !== $values) {
            $values = self::textAreaToArray($values);
            $labels = self::textAreaToArray($fieldType->getDisplayOption(self::LABELS_DISPLAY_OPTION));
            $counter = 0;
            foreach ($values as $value) {
                $choices[$value] = $labels[$counter++] ?? $value;
            }
        }

        $localePreferredFirst = $fieldType->getDisplayBoolOption(self::LOCALE_PREFERRED_FIRST_DISPLAY_OPTION, false);
        if (!$localePreferredFirst && $locale && isset($choices[$locale])) {
            $choices = [...[$locale => $choices[$locale]], ...\array_filter($choices, static fn ($l) => $l !== $locale)];
        }

        $choices = \array_flip($choices);

        if ($withLocalesVariable) {
            $options = $fieldType->getDisplayOptions();
            $options[self::LABELS_DISPLAY_OPTION] = \implode(PHP_EOL, \array_keys($choices));
            $options[self::VALUES_DISPLAY_OPTION] = \implode(PHP_EOL, $choices);
            $fieldType->setDisplayExtraOptions($options);
        }

        if (!$localePreferredFirst) {
            return $choices;
        }

        $language = $this->userManager->getUserLanguage();
        $key = \array_search($language, $choices, true);
        if (false === $key) {
            return $choices;
        }

        return \array_merge([$key => $language], $choices);
    }
}
