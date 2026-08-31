<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

use function Symfony\Component\Translation\t;

class ComputedFieldType extends DataFieldType
{
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        private readonly Environment $twig,
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
    }

    #[\Override]
    public function generateMcpSchema(FieldType $fieldType, callable $buildObjectSchema, bool $isOutputSchema = false): array
    {
        if (!$isOutputSchema) {
            return [];
        }

        $mcpOutputSchema = $fieldType->getExtraOption('mcpOutputSchema');
        if (!\is_string($mcpOutputSchema) || '' === \trim($mcpOutputSchema)) {
            return [];
        }

        try {
            $renderedSchema = $this->twig->createTemplate($mcpOutputSchema, \sprintf('%s:%s', $fieldType->getPath(), ':mcp-output-schema'))->render([
                'fieldType' => $fieldType,
                'contentType' => $fieldType->getContentType(),
            ]);

            return Json::decode($renderedSchema);
        } catch (\Throwable) {
            return [];
        }
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Computed from the raw-data';
    }

    #[\Override]
    public function generateMapping(FieldType $current): array
    {
        if (!empty($current->getMappingOptions()) && !empty($current->getMappingOptions()['mappingOptions'])) {
            try {
                $mapping = Json::mixedDecode((string) $current->getMappingOptions()['mappingOptions']);

                return [$current->getName() => $this->elasticsearchService->updateMapping($mapping)];
            } catch (\Exception) {
                // TODO send message to user, must move to service first
            }
        }

        return [];
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-gears';
    }

    #[\Override]
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            $out[$data->giveFieldType()->getName()] = $data->getRawData();
        }
    }

    #[\Override]
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
                ->get('mappingOptions')->remove('analyzer')->add('mappingOptions', CodeEditorType::class, [
                    'required' => false,
                    'language' => 'ace/mode/json',
                ])
            ->add('copy_to', TextType::class, [
                'required' => false,
            ]);
        }

        $optionsForm->remove('restrictionOptions');
        $optionsForm->remove('migrationOptions');
        if ($optionsForm->has('extraOptions')) {
            $optionsForm->get('extraOptions')->add('mcpOutputSchema', CodeEditorType::class, [
                'label' => t('field.mcp_output_schema', [], 'emsco-core'),
                'required' => false,
                'language' => 'ace/mode/twig',
            ]);
        }
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('value', HiddenType::class, [
            'required' => false,
        ]);
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        return ['value' => Json::encode($out)];
    }

    /**
     * @param array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $dataField = parent::reverseViewTransform($data, $fieldType);
        try {
            $value = Json::mixedDecode((string) $data['value']);
            $dataField->setRawData($value);
        } catch (\Exception) {
            $dataField->setRawData(null);
            $dataField->addMessage('ems was not able to parse the field');
        }

        return $dataField;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('displayTemplate', null);
        $resolver->setDefault('json', false);
        $resolver->setDefault('valueTemplate', null);
    }
}
