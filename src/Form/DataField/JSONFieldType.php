<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JSONFieldType extends DataFieldType
{
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
            'attr' => ['rows' => $options['rows']],
            'label' => (null != $options['label'] ? $options['label'] : $fieldType->getName()),
            'required' => false,
            'disabled' => $this->isDisabled($options),
        ]);
    }

    /**
     * @return array{'value': string}
     */
    public function viewTransform(DataField $dataField): array
    {
        $prettyPrint = (bool) $dataField->giveFieldType()->getDisplayOption('prettyPrint', false);

        return ['value' => Json::encode($dataField->getRawData(), $prettyPrint)];
    }

    /**
     * @param ?array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $dataField = parent::reverseViewTransform($data, $fieldType);

        $json = $data['value'] ?? null;
        if ($json) {
            try {
                $dataField->setRawData(Json::decode($json));
            } catch (\Throwable $e) {
                $dataField->addMessage($e->getMessage());
            }
        }

        return $dataField;
    }

    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            $out[$data->giveFieldType()->getName()] = $data->getRawData();
        }
    }

    public function getBlockPrefix(): string
    {
        return 'bypassdatafield';
    }

    public function isValid(DataField &$dataField, DataField $parent = null, mixed &$masterRawData = null): bool
    {
        if ($this->hasDeletedParent($parent)) {
            return true;
        }

        return parent::isValid($dataField, $parent, $masterRawData);
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
        $resolver->setDefault('rows', null);
        $resolver->setDefault('prettyPrint', false);
    }

    public function generateMapping(FieldType $current): array
    {
        if (!empty($current->getMappingOptions()) && !empty($current->getMappingOptions()['mappingOptions'])) {
            return [$current->getName(), Json::decode($current->getMappingOptions()['mappingOptions'])];
        }

        return [];
    }

    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        $optionsForm->get('displayOptions')
            ->add('rows', IntegerType::class, ['required' => false])
            ->add('prettyPrint', CheckboxType::class, ['required' => false]);

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
                ->remove('index')
                ->remove('analyzer')
                ->add('mappingOptions', TextareaType::class, [
                    'required' => false,
                    'attr' => ['rows' => 8],
            ]);
        }
    }
}
