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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MultiplexedTabContainerFieldType extends DataFieldType
{
    private const string LOCALE_PREFERRED_FIRST_DISPLAY_OPTION = 'localePreferredFirst';
    private const string LABELS_DISPLAY_OPTION = 'labels';
    private const string VALUES_DISPLAY_OPTION = 'values';
    private const string ICON_DISPLAY_OPTION = 'icon';

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        private readonly UserManager $userManager
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
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
        $resolver->setDefault(self::ICON_DISPLAY_OPTION, null);
    }

    #[\Override]
    public function generateMapping(FieldType $current): array
    {
        $values = $current->getDisplayOption(self::VALUES_DISPLAY_OPTION);
        if (null === $values) {
            return [];
        }

        $values = self::textAreaToArray($values);
        $mapping = [];
        foreach ($values as $value) {
            $mapping[$value] = ['properties' => []];
        }

        return $mapping;
    }

    #[\Override]
    public static function getJsonNames(FieldType $current): array
    {
        $values = $current->getDisplayOption(self::VALUES_DISPLAY_OPTION);
        if (null === $values) {
            return [];
        }

        return self::textAreaToArray($values);
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

        foreach ($this->getChoices($fieldType, $options['locale']) as $label => $value) {
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
        $labels = $fieldType->getDisplayOption(self::LABELS_DISPLAY_OPTION) ?? '';
        $values = $fieldType->getDisplayOption(self::VALUES_DISPLAY_OPTION);
        if (null !== $values) {
            $values = self::textAreaToArray($values);
            $labels = self::textAreaToArray($labels);
            $counter = 0;
            foreach ($values as $value) {
                $choices[$value] = $labels[$counter++] ?? $value;
            }
        }

        if ($locale && isset($choices[$locale])) {
            $choices = [...[$locale => $choices[$locale]], ...\array_filter($choices, static fn ($l) => $l !== $locale)];
        }

        $choices = \array_flip($choices);

        $localePreferredFirst = $fieldType->getDisplayBoolOption(self::LOCALE_PREFERRED_FIRST_DISPLAY_OPTION, false);
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
