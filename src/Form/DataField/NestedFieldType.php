<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\ElasticsearchService;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

/**
 * Defined a Nested obecjt.
 * It's used to  groups subfields together.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class NestedFieldType extends DataFieldType
{
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        TokenStorageInterface $tokenStorage,
        private readonly DataService $dataService,
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService, $tokenStorage);
    }

    #[\Override]
    public function generateMcpSchema(FieldType $fieldType, callable $buildObjectSchema, bool $isOutputSchema = false): array
    {
        return $buildObjectSchema($fieldType->getValidChildren());
    }

    #[\Override]
    public function getLabel(): TranslatableMessage
    {
        return t('field_type.nested', [], 'emsco-core');
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'glyphicon glyphicon-modal-window';
    }

    #[\Override]
    public function importData(DataField $dataField, array|string|int|float|bool|null $sourceArray, bool $isMigration): array
    {
        $migrationOptions = $dataField->giveFieldType()->getMigrationOptions();
        if (!$isMigration || [] === $migrationOptions || !$migrationOptions['protected']) {
            foreach ($dataField->getChildren() as $child) {
                if (\is_array($sourceArray)) {
                    $this->dataService->updateDataValue($child, $sourceArray, $isMigration);
                }
            }
        }

        return [$dataField->giveFieldType()->getName()];
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        /** @var FieldType $fieldType */
        foreach ($fieldType->getChildren() as $fieldType) {
            $this->buildChildForm($fieldType, $options, $builder);
        }
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'container_field_type';
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['icon'] = $options['icon'];
        $view->vars['multiple'] = $options['multiple'];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('multiple', false);
    }

    #[\Override]
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (null == $data->giveFieldType()) {
            $tmp = [];
            /** @var DataField $child */
            foreach ($data->getChildren() as $child) {
                $class = $this->formRegistry->getType($child->giveFieldType()->getType());

                if (\method_exists($class, 'buildObjectArray')) {
                    $class->buildObjectArray($child, $tmp);
                }
            }
            $out[] = $tmp;
        } elseif (!$data->giveFieldType()->getDeleted()) {
            $out[$data->giveFieldType()->getName()] = [];
        }
    }

    #[\Override]
    public static function isNested(): bool
    {
        return true;
    }

    #[\Override]
    public static function isContainer(): bool
    {
        /* this kind of compound field may contain children */
        return true;
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');
        $optionsForm->remove('mappingOptions');
        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
            'label' => t('field.icon', [], 'emsco-core'),
            'required' => false,
        ]);
    }

    #[\Override]
    public function generateMapping(FieldType $current): array
    {
        return [
            $current->getName() => [
                'type' => 'nested',
                'properties' => [],
            ], ];
    }
}
