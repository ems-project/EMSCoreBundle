<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\DBAL\ReleaseStatusEnumType;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Form\Data\Condition\NotEmpty;
use EMS\CoreBundle\Form\Data\Condition\Terms;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Form\Form\ReleaseType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\ReleaseRevisionService;
use EMS\CoreBundle\Service\ReleaseService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReleaseController extends AbstractController
{
    private LoggerInterface $logger;
    private ReleaseService $releaseService;
    private ReleaseRevisionService $releaseRevisionService;

    public function __construct(LoggerInterface $logger, ReleaseService $releaseService, ReleaseRevisionService $releaseRevisionService)
    {
        $this->logger = $logger;
        $this->releaseService = $releaseService;
        $this->releaseRevisionService = $releaseRevisionService;
    }

    public function ajaxDataTable(Request $request): Response
    {
        $table = $this->initTable();
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function index(Request $request): Response
    {
        $table = $this->initTable();
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::DELETE_ACTION:
                        $this->releaseService->deleteByIds($table->getSelected());
                        break;
                    default:
                        $this->logger->error('log.controller.release.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.release.unknown_action');
            }

            return $this->redirectToRoute('ems_core_release_index');
        }

        return $this->render('@EMSCore/release/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function add(Request $request): Response
    {
        $form2 = $this->createForm(ReleaseType::class, new Release());
        $form2->handleRequest($request);
        if ($form2->isSubmitted() && $form2->isValid()) {
            $release = $this->releaseService->add($form2->getViewData());
            /** @var ClickableInterface $button */
            $button = $form2->get('saveAndClose');
            $nextAction = $button->isClicked() ? 'ems_core_release_index' : 'ems_core_release_edit';

            return $this->redirectToRoute($nextAction, ['release' => $release->getId()]);
        }

        return $this->render('@EMSCore/release/add.html.twig', [
            'form_release' => $form2->createView(),
        ]);
    }

    public function edit(Request $request, Release $release, string $view = '@EMSCore/release/edit.html.twig'): Response
    {
        if (!empty($release->getRevisionsIds())) {
            $table = new QueryTable($this->releaseRevisionService, 'revisions-to-publish-to-remove', $this->generateUrl('ems_core_release_ajax_data_table'), [
                'option' => TableAbstract::REMOVE_ACTION,
                'selected' => !empty($release) ? $release->getRevisionsIds() : [],
                'source' => (!empty($release->getEnvironmentSource())) ? $release->getEnvironmentSource()->getId() : null,
                'target' => (!empty($release->getEnvironmentTarget())) ? $release->getEnvironmentTarget()->getId() : null,
            ]);
            $table->setMassAction(false);
            $table->setIdField('emsLink');
            $labelColumn = $table->addColumn('release.revision.index.column.label', 'item_labelField');
            $table->addColumn('release.revision.index.column.CT', 'content_type_singular_name');
            $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.minRevId', 'minrevidstatus', '@EMSCore/release/columns/revisions.html.twig'));
            $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.maxRevId', 'maxrevidstatus', '@EMSCore/release/columns/revisions.html.twig'));
            $table->setSelected($release->getRevisionsIds());
            $table->addTableAction(TableAbstract::REMOVE_ACTION, 'fa fa-minus', 'release.revision.actions.remove', 'release.revision.actions.remove_confirm');

            $form = $this->createForm(TableType::class, $table);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                    switch ($action->getName()) {
                        case EntityTable::REMOVE_ACTION:
                            $this->releaseService->removeRevisions($release, $table->getSelected());
                            break;
                        default:
                            $this->logger->error('log.controller.release.unknown_action');
                    }
                } else {
                    $this->logger->error('log.controller.release.unknown_action');
                }

                return $this->redirectToRoute('ems_core_release_edit', ['release' => $release->getId()]);
            }
        }

        $form2 = $this->createForm(ReleaseType::class, $release);
        $form2->handleRequest($request);
        if ($form2->isSubmitted() && $form2->isValid()) {
            $this->releaseService->update($release);
            /** @var ClickableInterface $button */
            $button = $form2->get('saveAndClose');
            $nextAction = $button->isClicked() ? 'ems_core_release_index' : 'ems_core_release_edit';

            return $this->redirectToRoute($nextAction, ['release' => $release->getId()]);
        }

        return $this->render($view, [
            'form' => isset($form) ? $form->createView() : null,
            'form_release' => $form2->createView(),
        ]);
    }

    public function viewRevisions(Request $request, Release $release, string $view = '@EMSCore/release/revisions/view.html.twig'): Response
    {
        if (!empty($release->getRevisionsIds())) {
            $table = new QueryTable($this->releaseRevisionService, 'revisions-to-publish-to-remove', $this->generateUrl('ems_core_release_ajax_data_table'), [
                'option' => TableAbstract::EXPORT_ACTION,
                'selected' => !empty($release) ? $release->getRevisionsIds() : [],
                'source' => (!empty($release->getEnvironmentSource())) ? $release->getEnvironmentSource()->getId() : null,
                'target' => (!empty($release->getEnvironmentTarget())) ? $release->getEnvironmentTarget()->getId() : null,
            ]);
            $table->setMassAction(false);
            $table->setIdField('emsLink');
            $labelColumn = $table->addColumn('release.revision.index.column.label', 'item_labelField');
            $table->addColumn('release.revision.index.column.CT', 'content_type_singular_name');
            $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.maxRevId', 'maxrevidstatus', '@EMSCore/release/columns/revisions.html.twig'));
            $table->setSelected($release->getRevisionsIds());
            $form = $this->createForm(TableType::class, $table);
        }

        return $this->render($view, [
            'form' => isset($form) ? $form->createView() : null,
        ]);
    }

    public function delete(Release $release): Response
    {
        $this->releaseService->delete($release);

        return $this->redirectToRoute('ems_core_release_index');
    }

    public function changeStatus(Release $release, string $status): Response
    {
        $release->setStatus($status);
        $this->releaseService->update($release);

        return $this->redirectToRoute('ems_core_release_index');
    }

    public function addRevisions(Request $request, Release $release): Response
    {
        $table = new QueryTable($this->releaseRevisionService, 'revisions-to-publish', $this->generateUrl('ems_core_release_ajax_data_table'), [
            'option' => TableAbstract::ADD_ACTION,
            'selected' => $release->getRevisionsIds(),
            'source' => (!empty($release->getEnvironmentSource())) ? $release->getEnvironmentSource()->getId() : null,
            'target' => (!empty($release->getEnvironmentTarget())) ? $release->getEnvironmentTarget()->getId() : null,
        ]);
        $table->setMassAction(false);
        $table->setIdField('emsLink');
        $labelColumn = $table->addColumn('release.revision.index.column.label', 'item_labelField');
        $table->addColumn('release.revision.index.column.CT', 'content_type_singular_name');
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.minRevId', 'minrevid', '@EMSCore/release/columns/revisions.html.twig'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.maxRevId', 'maxrevid', '@EMSCore/release/columns/revisions.html.twig'));
        $table->addTableAction(TableAbstract::ADD_ACTION, 'fa fa-plus', 'release.revision.actions.add', 'release.revision.actions.add_confirm');
        $table->setSelected($release->getRevisionsIds());

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::ADD_ACTION:
                        $this->releaseService->addRevisions($release, $table->getSelected());
                        break;
                    default:
                        $this->logger->error('log.controller.release.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.release.unknown_action');
            }

            return $this->redirectToRoute('ems_core_release_index');
        }

        return $this->render('@EMSCore/release/revisions.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function releasePublish(Request $request, Release $release): Response
    {
        $this->releaseService->publishRelease($release);

        return $this->redirectToRoute('ems_core_release_index');
    }

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->releaseService, $this->generateUrl('ems_core_release_ajax_data_table'));
        $table->addColumn('release.index.column.name', 'name');
        $table->addColumnDefinition(new DatetimeTableColumn('release.index.column.execution_date', 'executionDate'));
        $table->addColumn('release.index.column.status', 'status');
        $table->addColumn('release.index.column.env_source', 'environmentSource');
        $table->addColumn('release.index.column.env_target', 'environmentTarget');
        $table->addItemGetAction('ems_core_release_view', 'release.actions.show', 'eye')
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::APPLIED_STATUS, ReleaseStatusEnumType::SCHEDULED_STATUS]));
        $table->addItemGetAction('ems_core_release_edit', 'release.actions.edit', 'pencil')
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::WIP_STATUS]));
        $table->addItemGetAction('ems_core_release_add_revisions', 'release.actions.add_revisions', 'plus')
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::WIP_STATUS]));
        $table->addItemGetAction('ems_core_release_set_status', 'release.actions.set_status_ready', 'play', ['status' => ReleaseStatusEnumType::READY_STATUS])
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::WIP_STATUS]))
        ->addCondition(new NotEmpty('revisionsIds'));
        $table->addItemGetAction('ems_core_release_set_status', 'release.actions.set_status_wip', 'rotate-left', ['status' => ReleaseStatusEnumType::WIP_STATUS])
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::CANCELED_STATUS]));
        $table->addItemPostAction('ems_core_release_publish', 'release.actions.publish_release', 'toggle-on', 'release.actions.publish_confirm')
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::READY_STATUS]));
        $table->addItemGetAction('ems_core_release_set_status', 'release.actions.set_status_canceled', 'ban', ['status' => ReleaseStatusEnumType::CANCELED_STATUS])
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::READY_STATUS]));
        $table->addItemPostAction('ems_core_release_delete', 'release.actions.delete', 'trash', 'release.actions.delete_confirm');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'release.actions.delete_selected', 'release.actions.delete_selected_confirm');

        return $table;
    }
}
