<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Core\Form\FormManager;
use EMS\CoreBundle\Core\Revision\RawDataTransformer;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\FormPickerType;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;
use EMS\CoreBundle\Service\ElasticsearchService;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class FormFieldType extends DataFieldType
{
    public function __construct(
        protected AuthorizationCheckerInterface $authorizationChecker,
        protected FormRegistryInterface $formRegistry,
        protected ElasticsearchService $elasticsearchService,
        protected FieldTypeType $fieldTypeType,
        protected FormManager $formManager,
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
    }

    public static function isVisible(): bool
    {
        return false;
    }

    public function getLabel(): string
    {
        return 'Refers to a form entity';
    }

    public function getBlockPrefix(): string
    {
        return 'form_field_type';
    }

    public function postFinalizeTreatment(string $type, string $id, DataField $dataField, mixed $previousData): mixed
    {
        if (!empty($previousData[$dataField->giveFieldType()->getName()])) {
            return $previousData[$dataField->giveFieldType()->getName()];
        }

        return null;
    }

    public function importData(DataField $dataField, array|string|int|float|bool|null $sourceArray, bool $isMigration): array
    {
        throw new \Exception('This method should never be called');
    }

    public static function getIcon(): string
    {
        return 'fa fa-sitemap';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldType = $builder->getOptions()['metadata'];
        if (!$fieldType instanceof FieldType) {
            throw new \RuntimeException('Unexpected non-FieldType object');
        }

        $referredFieldType = $this->getReferredFieldType($fieldType);
        foreach ($referredFieldType->getChildren() as $child) {
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'form' => null,
        ]);
    }

    public function buildObjectArray(DataField $data, array &$out): void
    {
    }

    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        $optionsForm->remove('mappingOptions');
        $optionsForm->remove('migrationOptions');
        $optionsForm->get('restrictionOptions')->remove('mandatory');
        $optionsForm->get('restrictionOptions')->remove('mandatory_if');
        $optionsForm->get('displayOptions')->remove('label');
        $optionsForm->get('displayOptions')->remove('class');
        $optionsForm->get('displayOptions')->remove('helptext');
        $optionsForm->get('displayOptions')->remove('lastOfRow');
        $optionsForm->get('displayOptions')->add('form', FormPickerType::class, [
            'required' => false,
        ]);
    }

    public static function isVirtual(array $option = []): bool
    {
        return true;
    }

    public static function filterSubField(array $data, array $option): array
    {
        return $data;
    }

    public static function getJsonNames(FieldType $current): array
    {
        return [];
    }

    public function generateMapping(FieldType $current): array
    {
        return $this->fieldTypeType->generateMapping($this->getReferredFieldType($current));
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultOptions(string $name): array
    {
        return [
            'displayOptions' => [
                'form' => null,
            ],
            'mappingOptions' => [
            ],
            'restrictionOptions' => [
            ],
            'extraOptions' => [
            ],
            'raw_data' => [
            ],
        ];
    }

    public function getReferredFieldType(FieldType $fieldType): FieldType
    {
        $formName = $fieldType->getDisplayOption('form');

        return $this->formManager->getByName($formName)->getFieldType();
    }

    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        if (!\is_array($data)) {
            return parent::reverseViewTransform($data, $fieldType);
        }
        $referredFieldType = $this->getReferredFieldType($fieldType);

        return parent::reverseViewTransform(RawDataTransformer::reverseTransform($referredFieldType, $data), $fieldType);
    }

    public function viewTransform(DataField $dataField)
    {
        $rawData = $dataField->getRawData();
        if (!\is_array($rawData)) {
            return parent::viewTransform($dataField);
        }
        $referredFieldType = $this->getReferredFieldType($dataField->giveFieldType());

        return RawDataTransformer::transform($referredFieldType, $rawData);
    }
}
