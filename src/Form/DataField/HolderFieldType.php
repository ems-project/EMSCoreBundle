<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HolderFieldType extends DataFieldType
{
    public function getLabel(): string
    {
        return 'Invisible container (Holder)';
    }

    public function getBlockPrefix(): string
    {
        return 'holder_field_type';
    }

    /**
     * {@inheritDoc}
     */
    public function postFinalizeTreatment(string $type, string $id, DataField $dataField, ?array $previousData): ?array
    {
        if (!empty($previousData[$dataField->giveFieldType()->getName()])) {
            return $previousData[$dataField->giveFieldType()->getName()];
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function importData(DataField $dataField, array|string|int|float|bool|null $sourceArray, bool $isMigration): array
    {
        throw new \Exception('This method should never be called');
    }

    public static function getIcon(): string
    {
        return 'fa fa-square-o';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldType = $builder->getOptions()['metadata'];
        if (!$fieldType instanceof FieldType) {
            throw new \RuntimeException('Unexpected non-FieldType entity');
        }

        foreach ($fieldType->getChildren() as $child) {
            $this->buildChildForm($child, $options, $builder);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('is_visible', false);
    }

    /**
     * {@inheritDoc}
     */
    public function buildObjectArray(DataField $data, array &$out): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public static function isContainer(): bool
    {
        return true;
    }

    public static function isVisible(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        $optionsForm->remove('mappingOptions');
        $optionsForm->remove('migrationOptions');
        $optionsForm->remove('displayOptions');
        $optionsForm->get('restrictionOptions')->remove('mandatory');
        $optionsForm->get('restrictionOptions')->remove('mandatory_if');
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
    public static function getJsonNames(FieldType $current): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function generateMapping(FieldType $current): array
    {
        return [];
    }
}
