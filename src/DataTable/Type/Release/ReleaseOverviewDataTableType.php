<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Release;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Form\Data\Condition\NotEmpty;
use EMS\CoreBundle\Form\Data\Condition\Terms;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ReleaseService;

class ReleaseOverviewDataTableType extends AbstractEntityTableType
{
    public function __construct(ReleaseService $releaseService, private readonly string $templateNamespace)
    {
        parent::__construct($releaseService);
    }

    public function build(EntityTable $table): void
    {
        $table->setDefaultOrder('executionDate', 'desc');
        $table->addColumn('release.index.column.name', 'name');
        $table->addColumnDefinition(new DatetimeTableColumn('release.index.column.execution_date', 'executionDate'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.index.column.status', 'status', "@$this->templateNamespace/release/columns/revisions.html.twig"));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.index.column.docs_count', 'docs_count', "@$this->templateNamespace/release/columns/revisions.html.twig"))->setCellClass('text-right');

        $table->addColumnDefinition(new TemplateBlockTableColumn('release.index.column.env_source', 'environmentSource', "@$this->templateNamespace/release/columns/revisions.html.twig"));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.index.column.env_target', 'environmentTarget', "@$this->templateNamespace/release/columns/revisions.html.twig"));
        $table->addItemGetAction(Routes::RELEASE_VIEW, 'release.actions.show', 'eye')
            ->addCondition(new Terms('status', [Release::APPLIED_STATUS, Release::SCHEDULED_STATUS, Release::READY_STATUS]));
        $table->addItemGetAction(Routes::RELEASE_EDIT, 'release.actions.edit', 'pencil')
            ->addCondition(new Terms('status', [Release::WIP_STATUS]));
        $table->addItemGetAction(Routes::RELEASE_ADD_REVISIONS, 'release.actions.add_revisions', 'plus')
            ->addCondition(new Terms('status', [Release::WIP_STATUS]));
        $table->addItemGetAction(Routes::RELEASE_SET_STATUS, 'release.actions.set_status_ready', 'play', ['status' => Release::READY_STATUS])
            ->addCondition(new Terms('status', [Release::WIP_STATUS]))
            ->addCondition(new NotEmpty('revisionsOuuids'));
        $table->addItemGetAction(Routes::RELEASE_SET_STATUS, 'release.actions.set_status_wip', 'rotate-left', ['status' => Release::WIP_STATUS])
            ->addCondition(new Terms('status', [Release::CANCELED_STATUS]));
        $table->addItemPostAction(Routes::RELEASE_PUBLISH, 'release.actions.publish_release', 'toggle-on', 'release.actions.publish_confirm')
            ->addCondition(new Terms('status', [Release::READY_STATUS]));
        $table->addItemGetAction(Routes::RELEASE_SET_STATUS, 'release.actions.set_status_canceled', 'ban', ['status' => Release::CANCELED_STATUS])
            ->addCondition(new Terms('status', [Release::READY_STATUS]));
        $table->addItemPostAction(Routes::RELEASE_DELETE, 'release.actions.delete', 'trash', 'release.actions.delete_confirm')
            ->setButtonType('outline-danger');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'release.actions.delete_selected', 'release.actions.delete_selected_confirm')->setCssClass('btn btn-outline-danger');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_PUBLISHER];
    }
}
