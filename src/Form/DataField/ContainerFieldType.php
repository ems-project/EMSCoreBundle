<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use EMS\CoreBundle\Form\Field\IconPickerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
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
        return 'glyphicon glyphicon-modal-window';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /* get the metadata associate */
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        foreach ($fieldType->getChildren() as $child) {
            if (!$child->getDeleted()) {
                /* merge the default options with the ones specified by the user */
                $options = \array_merge([
                        'metadata' => $child,
                        'label' => false,
                        'migration' => $options['migration'],
                        'with_warning' => $options['with_warning'],
                        'raw_data' => $options['raw_data'],
                        'disabled_fields' => $options['disabled_fields'],
                        'referrer-ems-id' => $options['referrer-ems-id'],
                ], $child->getDisplayOptions());

                $builder->add($child->getName(), $child->getType(), $options);

                $builder->get($child->getName())
                    ->addViewTransformer(new DataFieldViewTransformer($child, $this->formRegistry))
                    ->addModelTransformer(new DataFieldModelTransformer($child, $this->formRegistry));
            }
        }
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /* give options for twig context */
        parent::buildView($view, $form, $options);
        $view->vars['icon'] = $options['icon'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        /* an optional icon can't be specified ritgh to the container label */
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
        /* this kind of compound field may contain children */
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        // container aren't mapped in elasticsearch
        $optionsForm->remove('mappingOptions');
        $optionsForm->remove('migrationOptions');
        $optionsForm->get('restrictionOptions')->remove('mandatory');
        $optionsForm->get('restrictionOptions')->remove('mandatory_if');
        // an optional icon can't be specified ritgh to the container label
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
