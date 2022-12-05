<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
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
class JSONFieldType extends DataFieldType
{
    /* to refactor */

    public function getLabel(): string
    {
        return 'JSON field';
    }

    public static function getIcon(): string
    {
        return 'fa fa-code';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];
        $builder->add('value', TextareaType::class, [
                'attr' => [
                        'rows' => $options['rows'],
                ],
                'label' => (null != $options['label'] ? $options['label'] : $fieldType->getName()),
                'required' => false,
                'disabled' => $this->isDisabled($options),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function viewTransform(DataField $dataField)
    {
        return ['value' => \json_encode($dataField->getRawData(), JSON_THROW_ON_ERROR)];
    }

    /**
     * {@inheritDoc}
     *
     * @param ?array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $dataValues = parent::reverseViewTransform($data, $fieldType);
        $options = $fieldType->getOptions();
        if (null === $data) {
            $dataValues->setRawData(null);
        } else {
            $json = @\json_decode((string) $data['value']);
            if (null === $json
                    && JSON_ERROR_NONE !== \json_last_error()) {
                $dataValues->setRawData($data['value']);
            } else {
                $dataValues->setRawData($json);
            }
        }

        return $dataValues;
    }

    /**
     * {@inheritDoc}
     */
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            /*
             * by default it serialize the text value.
             * It can be overrided.
             */
            $out[$data->giveFieldType()->getName()] = $data->getRawData();
        }
    }

    public function getBlockPrefix(): string
    {
        return 'bypassdatafield';
    }

    /**
     * {@inheritDoc}
     */
    public function isValid(DataField &$dataField, DataField $parent = null, mixed &$masterRawData = null): bool
    {
        if ($this->hasDeletedParent($parent)) {
            return true;
        }

        $isValid = parent::isValid($dataField, $parent, $masterRawData);
        $rawData = $dataField->getRawData();
        if (null !== $rawData) {
            $data = @\json_decode((string) $rawData);

            if (JSON_ERROR_NONE !== \json_last_error()) {
                $isValid = false;
                $dataField->addMessage('Not a valid JSON');
            }
        }

        return $isValid;
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /* get options for twig context */
        parent::buildView($view, $form, $options);
        $view->vars['icon'] = $options['icon'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('rows', null);
    }

    /**
     * {@inheritDoc}
     */
    public function generateMapping(FieldType $current): array
    {
        if (!empty($current->getMappingOptions()) && !empty($current->getMappingOptions()['mappingOptions'])) {
            return [$current->getName() => \json_decode((string) $current->getMappingOptions()['mappingOptions'], true, 512, JSON_THROW_ON_ERROR)];
        }

        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->remove('index')->remove('analyzer')->add('mappingOptions', TextareaType::class, [
                    'required' => false,
                    'attr' => [
                        'rows' => 8,
                    ],
            ]);
        }

        $optionsForm->get('displayOptions')->add('rows', IntegerType::class, [
                'required' => false,
        ]);
    }
}
