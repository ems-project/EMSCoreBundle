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
use EMS\CoreBundle\Routes;
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

    public function ajaxReleaseTable(Request $request): Response
    {
        $table = $this->initReleaseTable();
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function ajaxReleaseTableMemberRevisions(Request $request, Release $release): Response
    {
        $table = $this->getMemberRevisionsTable($release);
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function ajaxReleaseTableNonMemberRevisions(Request $request, Release $release): Response
    {
        $table = $this->getNonMemberRevisionsTable($release);
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function ajaxReleaseTablePublishedRevisions(Request $request, Release $release): Response
    {
        $table = $this->getPublishedRevisionsTable($release);
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function index(Request $request): Response
    {
        $table = $this->initReleaseTable();
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case TableAbstract::DELETE_ACTION:
                        $this->releaseService->deleteByIds($table->getSelected());
                        break;
                    default:
                        $this->logger->error('log.controller.release.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.release.unknown_action');
            }

            return $this->redirectToRoute(Routes::RELEASE_INDEX);
        }

        return $this->render('@EMSCore/release/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function add(Request $request): Response
    {
        $form = $this->createForm(ReleaseType::class, new Release());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $release = $this->releaseService->add($form->getViewData());
            $saveAnbClose = $form->get('saveAndClose');
            if (!$saveAnbClose instanceof ClickableInterface) {
                throw new \RuntimeException('Unexpected non clickable object');
            }
            $nextAction = $saveAnbClose->isClicked() ? Routes::RELEASE_INDEX : Routes::RELEASE_EDIT;

            return $this->redirectToRoute($nextAction, ['release' => $release->getId()]);
        }

        return $this->render('@EMSCore/release/add.html.twig', [
            'form_release' => $form->createView(),
        ]);
    }

    public function edit(Request $request, Release $release): Response
    {
        $revisionsTable = $this->getMemberRevisionsTable($release);

        $revisionsForm = $this->createForm(TableType::class, $revisionsTable);
        $revisionsForm->handleRequest($request);
        if ($revisionsForm->isSubmitted() && $revisionsForm->isValid()) {
            if ($revisionsForm instanceof Form && ($action = $revisionsForm->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case TableAbstract::REMOVE_ACTION:
                        $this->releaseService->removeRevisions($release, $revisionsTable->getSelected());
                        break;
                    default:
                        $this->logger->error('log.controller.release.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.release.unknown_action');
            }

            return $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $release->getId()]);
        }

        $releaseForm = $this->createForm(ReleaseType::class, $release);
        $releaseForm->handleRequest($request);
        if ($releaseForm->isSubmitted() && $releaseForm->isValid()) {
            $this->releaseService->update($release);
            $saveAnbClose = $releaseForm->get('saveAndClose');
            if (!$saveAnbClose instanceof ClickableInterface) {
                throw new \RuntimeException('Unexpected non clickable object');
            }
            $nextAction = $saveAnbClose->isClicked() ? Routes::RELEASE_INDEX : Routes::RELEASE_EDIT;

            return $this->redirectToRoute($nextAction, ['release' => $release->getId()]);
        }

        return $this->render('@EMSCore/release/edit.html.twig', [
            'form' => $revisionsForm->createView(),
            'form_release' => $releaseForm->createView(),
        ]);
    }

    public function viewRevisions(Release $release): Response
    {
        $table = $this->getPublishedRevisionsTable($release);
        $form = $this->createForm(TableType::class, $table);

        return $this->render('@EMSCore/release/revisions/view.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function delete(Release $release): Response
    {
        $this->releaseService->delete($release);

        return $this->redirectToRoute(Routes::RELEASE_INDEX);
    }

    public function changeStatus(Release $release, string $status): Response
    {
        $release->setStatus($status);
        $this->releaseService->update($release);

        return $this->redirectToRoute(Routes::RELEASE_INDEX);
    }

    public function addRevisions(Request $request, Release $release): Response
    {
        $table = $this->getNonMemberRevisionsTable($release);
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case TableAbstract::ADD_ACTION:
                        $this->releaseService->addRevisions($release, $table->getSelected());
                        break;
                    default:
                        $this->logger->error('log.controller.release.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.release.unknown_action');
            }

            return $this->redirectToRoute(Routes::RELEASE_INDEX);
        }

        return $this->render('@EMSCore/release/revisions.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function releasePublish(Request $request, Release $release): Response
    {
        $this->releaseService->publishRelease($release);

        return $this->redirectToRoute(Routes::RELEASE_INDEX);
    }

    private function initReleaseTable(): EntityTable
    {
        $table = new EntityTable($this->releaseService, $this->generateUrl(Routes::RELEASE_AJAX_DATA_TABLE));
        $table->addColumn('release.index.column.name', 'name');
        $table->addColumnDefinition(new DatetimeTableColumn('release.index.column.execution_date', 'executionDate'));
        $table->addColumn('release.index.column.status', 'status');
        $table->addColumn('release.index.column.env_source', 'environmentSource');
        $table->addColumn('release.index.column.env_target', 'environmentTarget');
        $table->addItemGetAction(Routes::RELEASE_VIEW, 'release.actions.show', 'eye')
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::APPLIED_STATUS, ReleaseStatusEnumType::SCHEDULED_STATUS]));
        $table->addItemGetAction(Routes::RELEASE_EDIT, 'release.actions.edit', 'pencil')
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::WIP_STATUS]));
        $table->addItemGetAction(Routes::RELEASE_ADD_REVISIONS, 'release.actions.add_revisions', 'plus')
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::WIP_STATUS]));
        $table->addItemGetAction(Routes::RELEASE_SET_STATUS, 'release.actions.set_status_ready', 'play', ['status' => ReleaseStatusEnumType::READY_STATUS])
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::WIP_STATUS]))
        ->addCondition(new NotEmpty('revisionsIds'));
        $table->addItemGetAction(Routes::RELEASE_SET_STATUS, 'release.actions.set_status_wip', 'rotate-left', ['status' => ReleaseStatusEnumType::WIP_STATUS])
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::CANCELED_STATUS]));
        $table->addItemPostAction(Routes::RELEASE_PUBLISH, 'release.actions.publish_release', 'toggle-on', 'release.actions.publish_confirm')
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::READY_STATUS]));
        $table->addItemGetAction(Routes::RELEASE_SET_STATUS, 'release.actions.set_status_canceled', 'ban', ['status' => ReleaseStatusEnumType::CANCELED_STATUS])
        ->addCondition(new Terms('status', [ReleaseStatusEnumType::READY_STATUS]));
        $table->addItemPostAction(Routes::RELEASE_DELETE, 'release.actions.delete', 'trash', 'release.actions.delete_confirm');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'release.actions.delete_selected', 'release.actions.delete_selected_confirm');

        return $table;
    }

    private function getMemberRevisionsTable(Release $release): QueryTable
    {
        $table = $this->getRevisionsTable($release, 'revisions-to-publish-to-remove', ReleaseRevisionService::QUERY_REVISIONS_IN_RELEASE, Routes::RELEASE_MEMBER_REVISION_AJAX);
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.minRevId', 'minrevid', '@EMSCore/release/columns/revisions.html.twig'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.maxRevId', 'maxrevid', '@EMSCore/release/columns/revisions.html.twig'));
        $table->addTableAction(TableAbstract::REMOVE_ACTION, 'fa fa-minus', 'release.revision.actions.remove', 'release.revision.actions.remove_confirm');

        return $table;
    }

    private function getNonMemberRevisionsTable(Release $release): QueryTable
    {
        $table = $this->getRevisionsTable($release, 'revisions-to-publish', TableAbstract::ADD_ACTION, Routes::RELEASE_NON_MEMBER_REVISION_AJAX);
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.minRevId', 'minrevid', '@EMSCore/release/columns/revisions.html.twig'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.maxRevId', 'maxrevid', '@EMSCore/release/columns/revisions.html.twig'));
        $table->addTableAction(TableAbstract::ADD_ACTION, 'fa fa-plus', 'release.revision.actions.add', 'release.revision.actions.add_confirm');

        return $table;
    }

    public function getPublishedRevisionsTable(Release $release): QueryTable
    {
        $table = $this->getRevisionsTable($release, 'revisions-to-publish-to-remove', ReleaseRevisionService::QUERY_REVISIONS_IN_PUBLISHED_RELEASE, Routes::RELEASE_PUBLISHED_REVISION_AJAX);
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.maxRevId', 'maxrevidstatus', '@EMSCore/release/columns/revisions.html.twig'));

        return $table;
    }

    private function getRevisionsTable(Release $release, string $queryName, string $option, string $route): QueryTable
    {
        $table = new QueryTable($this->releaseRevisionService, $queryName, $this->generateUrl($route, ['release' => $release->getId()]), [
            'option' => $option,
            'selected' => $release->getRevisionsIds(),
            'source' => $release->getEnvironmentSource(),
            'target' => $release->getEnvironmentTarget(),
        ]);
        $table->setMassAction(false);
        $table->setIdField('emsLink');
        $table->addColumn('release.revision.index.column.label', 'item_labelField');
        $table->addColumn('release.revision.index.column.CT', 'content_type_singular_name');
        $table->setSelected($release->getRevisionsIds());

        return $table;
    }
}
