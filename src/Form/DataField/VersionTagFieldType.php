<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Core\ContentType\Version\VersionOptions;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class VersionTagFieldType extends DataFieldType
{
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        FormRegistryInterface $formRegistry,
        ElasticsearchService $elasticsearchService,
        private readonly RevisionService $revisionService,
        private readonly EnvironmentService $environmentService,
        private readonly ContentTypeService $contentTypeService
    ) {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
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

        $emsId = $options['referrer-ems-id'] ?? null;
        $countEnvironments = 0;

        if ($emsId) {
            $revision = $this->revisionService->getByEmsLink(EMSLink::fromText($emsId));
            $countEnvironments = $revision ? $this->environmentService->getPublishedForRevision($revision, true)->count() : 0;
        }

        $notBlankNewVersion = $contentType->getVersionOptions()[VersionOptions::NOT_BLANK_NEW_VERSION];

        if (0 === $countEnvironments) {
            $choices = $this->contentTypeService->getVersionDefault($contentType);
            $placeholder = false;
        } else {
            $choices = $this->contentTypeService->getVersionTagsByContentType($contentType, $notBlankNewVersion);
            $placeholder = $notBlankNewVersion ? '' : false;
        }

        $builder->add('value', ChoiceType::class, [
            'constraints' => $notBlankNewVersion ? [new NotBlank()] : [],
            'label' => ($options['label'] ?? $fieldType->getName()),
            'placeholder' => $placeholder,
            'choices' => $choices,
            'help_html' => true,
            'help' => $options['helptext'],
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
