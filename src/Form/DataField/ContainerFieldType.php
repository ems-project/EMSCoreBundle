<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContainerFieldType extends DataFieldType
{
    public function getLabel(): string
    {
        return 'Visual container (invisible in Elasticsearch)';
    }

    public function getBlockPrefix(): string
    {
        return 'container_field_type';
    }

    /**
     * {@inheritDoc}
     */
    public function postFinalizeTreatment(string $type, string $id, DataField $dataField, mixed $previousData): mixed
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
        return 'glyphicon glyphicon-modal-window';
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

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['icon'] = $options['icon'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
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

    /**
     * {@inheritDoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        $optionsForm->remove('mappingOptions');
        $optionsForm->remove('migrationOptions');
        $optionsForm->get('restrictionOptions')->remove('mandatory');
        $optionsForm->get('restrictionOptions')->remove('mandatory_if');
        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
                'required' => false,
        ]);
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
