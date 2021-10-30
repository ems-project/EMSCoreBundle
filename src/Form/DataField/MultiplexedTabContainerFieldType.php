<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MultiplexedTabContainerFieldType extends DataFieldType
{
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
     * @param array<mixed> $options
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('displayOptions')->add('values', TextareaType::class, [
            'required' => false,
        ])->add('labels', TextareaType::class, [
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
        $resolver->setDefault('values', '');
        $resolver->setDefault('labels', '');
    }

    /**
     * @param bool $withPipeline
     *
     * @return array<mixed>
     */
    public function generateMapping(FieldType $current, $withPipeline): array
    {
        $values = $current->getDisplayOption('values');
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
     * @return string[]
     */
    public static function getJsonNames(FieldType $current): array
    {
        $values = $current->getDisplayOption('values');
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
     * @param array<mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldType = $builder->getOptions()['metadata'];
        if (!$fieldType instanceof FieldType) {
            throw new \RuntimeException('Unexpected FieldType type');
        }

        $labels = $fieldType->getDisplayOption('labels') ?? '';
        $values = $fieldType->getDisplayOption('values');
        if (null === $values) {
            return;
        }

        $values = self::textAreaToArray($values);
        $labels = self::textAreaToArray($labels);
        $counter = 0;
        foreach ($values as $value) {
            $builder->add($value, ContainerFieldType::class, [
                'metadata' => $fieldType,
                'label' => $labels[$counter++] ?? $value,
                'migration' => $options['migration'],
                'with_warning' => $options['with_warning'],
                'raw_data' => $options['raw_data'],
                'disabled_fields' => $options['disabled_fields'],
            ]);

            $builder->get($value)
                ->addViewTransformer(new DataFieldViewTransformer($fieldType, $this->formRegistry))
                ->addModelTransformer(new DataFieldModelTransformer($fieldType, $this->formRegistry));
        }
    }

    /**
     * @param array<mixed> $option
     */
    public static function isVirtual(array $option = []): bool
    {
        return true;
    }

    /**
     * @param array<mixed>|float|int|string|null $data
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
}
