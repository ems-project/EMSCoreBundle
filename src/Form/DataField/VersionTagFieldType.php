<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class VersionTagFieldType extends DataFieldType
{
    private RevisionService $revisionService;
    private EnvironmentService $environmentService;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        RevisionService $revisionService,
        EnvironmentService $environmentService
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);

        $this->revisionService = $revisionService;
        $this->environmentService = $environmentService;
    }

    public function getLabel(): string
    {
        return 'Select version tag';
    }

    public static function getIcon(): string
    {
        return 'fa fa-snowflake-o';
    }

    public function getBlockPrefix(): string
    {
        return 'ems_version_tag';
    }

    public function generateMapping(FieldType $current): array
    {
        return [$current->getName() => ['type' => 'keyword']];
    }

    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);

        $builder->get('options')
            ->remove('mappingOptions')
            ->remove('migrationOptions')
        ;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];
        $contentType = $fieldType->giveContentType();

        $versionTags = $contentType->getVersionTags();
        $emsId = $options['referrer-ems-id'] ?? null;
        $countEnvironments = 0;

        if ($emsId) {
            $revision = $this->revisionService->getByEmsLink(EMSLink::fromText($emsId));
            $countEnvironments = $revision ? $this->environmentService->findByRevision($revision, true)->count() : 0;
        }

        $builder->add('value', ChoiceType::class, [
            'label' => ($options['label'] ?? $fieldType->getName()),
            'required' => 0 === $countEnvironments,
            'disabled' => 0 === $countEnvironments,
            'choices' => \array_combine($versionTags, $versionTags),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            $out[$data->giveFieldType()->getName()] = $data->getRawData();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function viewTransform(DataField $dataField)
    {
        return ['value' => parent::viewTransform($dataField)];
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        return parent::reverseViewTransform($data['value'], $fieldType);
    }
}
