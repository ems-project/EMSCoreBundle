<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ComputedFieldType extends DataFieldType
{
    public function getLabel(): string
    {
        return 'Computed from the raw-data';
    }

    public function generateMapping(FieldType $current): array
    {
        if (!empty($current->getMappingOptions()) && !empty($current->getMappingOptions()['mappingOptions'])) {
            try {
                $mapping = \json_decode((string) $current->getMappingOptions()['mappingOptions'], true, 512, JSON_THROW_ON_ERROR);

                return [$current->getName() => $this->elasticsearchService->updateMapping($mapping)];
            } catch (\Exception) {
                // TODO send message to user, mustr move to service first
            }
        }

        return [];
    }

    public static function getIcon(): string
    {
        return 'fa fa-gears';
    }

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

    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('displayOptions')->add('valueTemplate', CodeEditorType::class, [
            'required' => false,
            'language' => 'ace/mode/twig',
        ])->add('json', CheckboxType::class, [
                'required' => false,
                'label' => 'Try to JSON decode',
        ])->add('displayTemplate', CodeEditorType::class, [
            'required' => false,
            'language' => 'ace/mode/twig',
        ]);

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm
                ->get('mappingOptions')->remove('index')->remove('analyzer')->add('mappingOptions', CodeEditorType::class, [
                    'required' => false,
                    'language' => 'ace/mode/json',
                ])
            ->add('copy_to', TextType::class, [
                    'required' => false,
            ]);
        }

        $optionsForm->remove('restrictionOptions');
        $optionsForm->remove('migrationOptions');
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('value', HiddenType::class, [
            'required' => false,
        ]);
    }

    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        return ['value' => \json_encode($out, JSON_THROW_ON_ERROR)];
    }

    /**
     * @param array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $dataField = parent::reverseViewTransform($data, $fieldType);
        try {
            $value = \json_decode((string) $data['value'], null, 512, JSON_THROW_ON_ERROR);
            $dataField->setRawData($value);
        } catch (\Exception) {
            $dataField->setRawData(null);
            $dataField->addMessage('ems was not able to parse the field');
        }

        return $dataField;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('displayTemplate', null);
        $resolver->setDefault('json', false);
        $resolver->setDefault('valueTemplate', null);
    }
}
