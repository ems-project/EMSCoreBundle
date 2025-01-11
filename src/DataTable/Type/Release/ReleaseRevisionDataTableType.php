<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Release;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Form\Data\Condition\NotEmpty;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ReleaseRevisionService;
use EMS\CoreBundle\Service\ReleaseService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReleaseRevisionDataTableType extends AbstractEntityTableType
{
    public const ACTION_ROLLBACK = 'rollback_action';

    public function __construct(
        ReleaseRevisionService $releaseRevisionService,
        private readonly ReleaseService $releaseService,
        private readonly string $templateNamespace,
    ) {
        parent::__construct($releaseRevisionService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        /** @var Release $release */
        $release = $table->getContext();

        $table->setMassAction(true);
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.CT', 'contentType', "@$this->templateNamespace/release/columns/release-revisions.html.twig"));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.document', 'label', "@$this->templateNamespace/release/columns/release-revisions.html.twig"));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.revision', 'revision', "@$this->templateNamespace/release/columns/release-revisions.html.twig"));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.action', 'action', "@$this->templateNamespace/release/columns/release-revisions.html.twig"));

        switch ($release->getStatus()) {
            case Release::WIP_STATUS:
                $table->addTableAction(TableAbstract::REMOVE_ACTION, 'fa fa-minus', 'release.revision.actions.remove', 'release.revision.actions.remove_confirm');
                break;
            case Release::APPLIED_STATUS:
                $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.still_in_target', 'stil_in_target', "@$this->templateNamespace/release/columns/release-revisions.html.twig"))->setLabelTransOption(['%target%' => $release->getEnvironmentTarget()->getLabel()]);
                $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.previous', 'previous', "@$this->templateNamespace/release/columns/release-revisions.html.twig"));
                $table->addDynamicItemGetAction(Routes::VIEW_REVISIONS, 'release.revision.index.column.compare', 'compress', ['type' => 'contentType', 'ouuid' => 'revisionOuuid', 'revisionId' => 'revision.id', 'compareId' => 'rollbackRevision.id'])->addCondition(new NotEmpty('rollbackRevision', 'revision'));
                $table->addTableAction(self::ACTION_ROLLBACK, 'fa fa-rotate-left', 'release.revision.table.rollback.action', 'release.revision.table.rollback.confirm');
                break;
        }
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_PUBLISHER];
    }

    #[\Override]
    public function getContext(array $options): Release
    {
        return $this->releaseService->getById($options['release_id']);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['release_id']);
    }
}
