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

use function Symfony\Component\Translation\t;

class ReleaseOverviewDataTableType extends AbstractEntityTableType
{
    public function __construct(
        ReleaseService $releaseService,
        private readonly string $templateNamespace
    ) {
        parent::__construct($releaseService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->setDefaultOrder('executionDate', 'desc');
        $table->addColumn(
            titleKey: t('field.name', [], 'emsco-core'),
            attribute: 'name'
        );
        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_execution', [], 'emsco-core'),
            attribute: 'executionDate'
        ));
        $table->addColumnDefinition(
            new TemplateBlockTableColumn(
                label: t('field.status', [], 'emsco-core'),
                blockName: 'status',
                template: \sprintf('@%s/release/columns/revisions.html.twig', $this->templateNamespace)
            )
        );
        $table->addColumnDefinition(new TemplateBlockTableColumn(t('release.index.column.docs_count', [], 'emsco-core'), 'docs_count', \sprintf('@%s/release/columns/revisions.html.twig', $this->templateNamespace)))->setCellClass('text-right');

        $table->addColumnDefinition(
            new TemplateBlockTableColumn(
                label: t('field.release_environment_source', [], 'emsco-core'),
                blockName: 'environmentSource',
                template: \sprintf('@%s/release/columns/revisions.html.twig', $this->templateNamespace)
            )
        );
        $table->addColumnDefinition(
            new TemplateBlockTableColumn(
                label: t('field.release_environment_target', [], 'emsco-core'),
                blockName: 'environmentTarget',
                template: \sprintf('@%s/release/columns/revisions.html.twig', $this->templateNamespace)
            )
        );
        $table->addItemGetAction(
            route: Routes::RELEASE_VIEW,
            labelKey: t('release.actions.show', [], 'emsco-core'),
            icon: 'eye',
            attributes: ['data-testid' => 'release-action-show']
        )->addCondition(new Terms('status', [Release::APPLIED_STATUS, Release::SCHEDULED_STATUS, Release::READY_STATUS]));
        $table->addItemGetAction(
            route: Routes::RELEASE_EDIT,
            labelKey: t('release.actions.edit', [], 'emsco-core'),
            icon: 'pencil',
            attributes: ['data-testid' => 'release-action-edit']
        )->addCondition(new Terms('status', [Release::WIP_STATUS]));
        $table->addItemGetAction(
            route: Routes::RELEASE_ADD_REVISIONS,
            labelKey: t('release.actions.add_publish', [], 'emsco-core'),
            icon: 'plus',
            routeParameters: ['type' => 'publish'],
            attributes: ['data-testid' => 'release-action-add-publish']
        )->addCondition(new Terms('status', [Release::WIP_STATUS]));
        $table->addItemGetAction(
            route: Routes::RELEASE_ADD_REVISIONS,
            labelKey: t('release.actions.add_unpublish', [], 'emsco-core'),
            icon: 'minus',
            routeParameters: ['type' => 'unpublish'],
            attributes: ['data-testid' => 'release-action-add-unpublish']
        )->addCondition(new Terms('status', [Release::WIP_STATUS]));
        $table->addItemGetAction(
            route: Routes::RELEASE_SET_STATUS,
            labelKey: t('release.actions.set_status_ready', [], 'emsco-core'),
            icon: 'play',
            routeParameters: ['status' => Release::READY_STATUS],
            attributes: ['data-testid' => 'release-action-set-status-ready']
        )->addCondition(new Terms('status', [Release::WIP_STATUS]))->addCondition(new NotEmpty('revisionsOuuids'));
        $table->addItemGetAction(
            route: Routes::RELEASE_SET_STATUS,
            labelKey: t('release.actions.set_status_wip', [], 'emsco-core'),
            icon: 'rotate-left',
            routeParameters: ['status' => Release::WIP_STATUS],
            attributes: ['data-testid' => 'release-action-set-status-wip']
        )->addCondition(new Terms('status', [Release::CANCELED_STATUS]));
        $table->addItemPostAction(
            route: Routes::RELEASE_PUBLISH,
            labelKey: t('release.actions.publish_release', [], 'emsco-core'),
            icon: 'toggle-on',
            messageKey: t('release.actions.publish_confirm', [], 'emsco-core'),
            attributes: ['data-testid' => 'release-action-publish']
        )->addCondition(new Terms('status', [Release::READY_STATUS]));
        $table->addItemGetAction(
            route: Routes::RELEASE_SET_STATUS,
            labelKey: t('release.actions.set_status_canceled', [], 'emsco-core'),
            icon: 'ban',
            routeParameters: ['status' => Release::CANCELED_STATUS],
            attributes: ['data-testid' => 'release-action-set-status-canceled']
        )->addCondition(new Terms('status', [Release::READY_STATUS]));
        $table->addItemPostAction(
            route: Routes::RELEASE_DELETE,
            labelKey: t('release.actions.delete', [], 'emsco-core'),
            icon: 'trash',
            messageKey: t('release.actions.delete_confirm', [], 'emsco-core'),
            attributes: ['data-testid' => 'release-action-delete']
        )->setButtonType('outline-danger');
        $table->addTableAction(
            name: TableAbstract::DELETE_ACTION,
            icon: 'fa fa-trash',
            labelKey: t('release.actions.delete_selected', [], 'emsco-core'),
            confirmationKey: t('release.actions.delete_selected_confirm', [], 'emsco-core')
        )->setCssClass('btn btn-outline-danger');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_PUBLISHER];
    }
}
