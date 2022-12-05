<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MultiplexedTabContainerFieldType extends DataFieldType
{
    private const LOCALE_PREFERRED_FIRST_DISPLAY_OPTION = 'localePreferredFirst';
    private const LABELS_DISPLAY_OPTION = 'labels';
    private const VALUES_DISPLAY_OPTION = 'values';
    private const ICON_DISPLAY_OPTION = 'icon';

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, private readonly UserService $userService)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
    }

    public function getLabel(): string
    {
        return 'Multiplexed Tab Container';
    }

    public static function isContainer(): bool
    {
        return true;
    }

    public static function isNested(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault(self::VALUES_DISPLAY_OPTION, '');
        $resolver->setDefault(self::LABELS_DISPLAY_OPTION, '');
        $resolver->setDefault(self::LOCALE_PREFERRED_FIRST_DISPLAY_OPTION, false);
        $resolver->setDefault(self::ICON_DISPLAY_OPTION, null);
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    public static function getJsonNames(FieldType $current): array
    {
        $values = $current->getDisplayOption(self::VALUES_DISPLAY_OPTION);
        if (null === $values) {
            return [];
        }

        return self::textAreaToArray($values);
    }

    public function getBlockPrefix(): string
    {
        return 'tabsfieldtype';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldType = $builder->getOptions()['metadata'];
        if (!$fieldType instanceof FieldType) {
            throw new \RuntimeException('Unexpected FieldType type');
        }
        foreach ($this->getChoices($fieldType) as $label => $value) {
            $builder->add($value, ContainerFieldType::class, [
                'metadata' => $fieldType,
                'label' => $label,
                'migration' => $options['migration'],
                'icon' => $options[self::ICON_DISPLAY_OPTION] ?? null,
                'with_warning' => $options['with_warning'],
                'raw_data' => $options['raw_data'],
                'disabled_fields' => $options['disabled_fields'],
                'referrer-ems-id' => $options['referrer-ems-id'],
            ]);

            $builder->get($value)
                ->addViewTransformer(new DataFieldViewTransformer($fieldType, $this->formRegistry))
                ->addModelTransformer(new DataFieldModelTransformer($fieldType, $this->formRegistry));
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function isVirtual(array $option = []): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
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
    private function getChoices(FieldType $fieldType): array
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
        $choices = \array_flip($choices);

        $localePreferredFirst = $fieldType->getDisplayOption(self::LOCALE_PREFERRED_FIRST_DISPLAY_OPTION);
        if (!\is_bool($localePreferredFirst) || !$localePreferredFirst) {
            return $choices;
        }

        $user = $this->userService->getCurrentUser(true);
        if (!$user instanceof User || null === $localePreferred = $user->getLocalePreferred()) {
            return $choices;
        }

        $key = \array_search($localePreferred, $choices, true);
        if (false === $key) {
            return $choices;
        }

        return \array_merge([$key => $localePreferred], $choices);
    }
}
