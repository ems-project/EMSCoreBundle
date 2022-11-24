<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\Revision;
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
    public const ROLLBACK_ACTION = 'rollback_action';
    private LoggerInterface $logger;
    private ReleaseService $releaseService;
    private ReleaseRevisionService $releaseRevisionService;

    public function __construct(LoggerInterface $logger, ReleaseService $releaseService, ReleaseRevisionService $releaseRevisionService)
    {
        $this->logger = $logger;
        $this->releaseService = $releaseService;
        $this->releaseRevisionService = $releaseRevisionService;
    }

    public function ajaxReleaseTable(Request $request, ?Revision $revision = null): Response
    {
        if (null === $revision) {
            $table = $this->initReleaseTable();
        } else {
            $table = $this->initAddToReleaseTable($revision);
        }
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
        $form = $this->createForm(ReleaseType::class, new Release(), [
            'add' => true,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $release = $this->releaseService->add($form->getViewData());

            return $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $release->getId()]);
        }

        return $this->render('@EMSCore/release/add.html.twig', [
            'form_release' => $form->createView(),
        ]);
    }

    public function edit(Request $request, Release $release): Response
    {
        $revisionsTable = $this->getMemberRevisionsTable($release);

        $revisionsForm = $this->createForm(TableType::class, $revisionsTable, [
            'title_label' => 'release.revision.view.title',
        ]);
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
            'release' => $release,
        ]);
    }

    public function view(Request $request, Release $release): Response
    {
        $table = $this->getMemberRevisionsTable($release);
        $form = $this->createForm(TableType::class, $table, [
            'title_label' => 'release.revision.view.title',
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case self::ROLLBACK_ACTION:
                        $rollback = $this->releaseService->rollback($release, $table->getSelected());

                        return $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $rollback->getId()]);
                    default:
                        $this->logger->error('log.controller.release.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.release.unknown_action');
            }

            return $this->redirectToRoute(Routes::RELEASE_VIEW, ['release' => $release->getId()]);
        }

        return $this->render('@EMSCore/release/view.html.twig', [
            'form' => $form->createView(),
            'release' => $release,
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

    public function addRevisionById(Release $release, Revision $revision): Response
    {
        $this->releaseService->addRevision($release, $revision);

        return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
            'type' => $revision->giveContentType()->getName(),
            'ouuid' => $revision->getOuuid(),
            'revisionId' => $revision->getId(),
        ]);
    }

    public function addRevision(Release $release, string $emsLinkToAdd): Response
    {
        $this->releaseService->addRevisions($release, [$emsLinkToAdd]);

        return $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $release->getId()]);
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

            return $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $release->getId()]);
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

    public function pickRelease(Request $request, Revision $revision): Response
    {
        $table = $this->initAddToReleaseTable($revision);
        $form = $this->createForm(TableType::class, $table);

        return $this->render('@EMSCore/release/add-to-release.html.twig', [
            'form' => $form->createView(),
            'revision' => $revision,
        ]);
    }

    private function initAddToReleaseTable(Revision $revision): EntityTable
    {
        $table = new EntityTable($this->releaseService, $this->generateUrl(Routes::DATA_PICK_A_RELEASE_AJAX_DATA_TABLE, ['revision' => $revision->getId()]), $revision);
        $this->addBaseReleaseTableColumns($table);
        $table->addItemPostAction(Routes::DATA_ADD_REVISION_TO_RELEASE, 'data.actions.add_to_release', 'plus', 'data.actions.add_to_release_confirm', ['revision' => $revision->getId()])->setButtonType('primary');

        return $table;
    }

    private function initReleaseTable(): EntityTable
    {
        $table = new EntityTable($this->releaseService, $this->generateUrl(Routes::RELEASE_AJAX_DATA_TABLE));
        $this->addBaseReleaseTableColumns($table);
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.index.column.env_source', 'environmentSource', '@EMSCore/release/columns/revisions.html.twig'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.index.column.env_target', 'environmentTarget', '@EMSCore/release/columns/revisions.html.twig'));
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

        return $table;
    }

    private function getMemberRevisionsTable(Release $release): EntityTable
    {
        $table = new EntityTable($this->releaseRevisionService, $this->generateUrl(Routes::RELEASE_MEMBER_REVISION_AJAX, ['release' => $release->getId()]), $release);
        $table->setMassAction(true);
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.CT', 'contentType', '@EMSCore/release/columns/release-revisions.html.twig'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.document', 'label', '@EMSCore/release/columns/release-revisions.html.twig'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.revision', 'revision', '@EMSCore/release/columns/release-revisions.html.twig'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.action', 'action', '@EMSCore/release/columns/release-revisions.html.twig'));

        switch ($release->getStatus()) {
            case Release::WIP_STATUS:
                $table->addTableAction(TableAbstract::REMOVE_ACTION, 'fa fa-minus', 'release.revision.actions.remove', 'release.revision.actions.remove_confirm');
                break;
            case Release::APPLIED_STATUS:
                $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.still_in_target', 'stil_in_target', '@EMSCore/release/columns/release-revisions.html.twig'))->setLabelTransOption(['%target%' => $release->getEnvironmentTarget()->getLabel()]);
                $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.previous', 'previous', '@EMSCore/release/columns/release-revisions.html.twig'));
                $table->addDynamicItemGetAction(Routes::VIEW_REVISIONS, 'release.revision.index.column.compare', 'compress', ['type' => 'contentType', 'ouuid' => 'revisionOuuid', 'revisionId' => 'revision.id', 'compareId' => 'revisionBeforePublish.id'])->addCondition(new NotEmpty('revisionBeforePublish', 'revision'));
                $table->addTableAction(self::ROLLBACK_ACTION, 'fa fa-rotate-left', 'release.revision.table.rollback.action', 'release.revision.table.rollback.confirm');
                break;
        }

        return $table;
    }

    private function getNonMemberRevisionsTable(Release $release): QueryTable
    {
        $table = new QueryTable($this->releaseRevisionService, 'revisions-to-publish', $this->generateUrl(Routes::RELEASE_NON_MEMBER_REVISION_AJAX, ['release' => $release->getId()]), $release);
        $table->setMassAction(true);
        $table->setLabelAttribute('item_labelField');
        $table->setIdField('emsLink');
        $table->addColumn('release.revision.index.column.label', 'item_labelField');
        $table->addColumn('release.revision.index.column.CT', 'content_type_singular_name');
        $table->setSelected($release->getRevisionsOuuids());
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.minRevId', 'minrevid', '@EMSCore/release/columns/revisions.html.twig'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.maxRevId', 'maxrevid', '@EMSCore/release/columns/revisions.html.twig'));
        $table->addTableAction(TableAbstract::ADD_ACTION, 'fa fa-plus', 'release.revision.actions.add', 'release.revision.actions.add_confirm');
        $table->addDynamicItemPostAction(Routes::RELEASE_ADD_REVISION, 'release.revision.action.add', 'plus', 'release.revision.actions.add_confirm', ['release' => \sprintf('%d', $release->getId()), 'emsLinkToAdd' => 'emsLink']);

        return $table;
    }

    private function addBaseReleaseTableColumns(EntityTable $table): void
    {
        $table->setDefaultOrder('executionDate', 'desc');
        $table->addColumn('release.index.column.name', 'name');
        $table->addColumnDefinition(new DatetimeTableColumn('release.index.column.execution_date', 'executionDate'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.index.column.status', 'status', '@EMSCore/release/columns/revisions.html.twig'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.index.column.docs_count', 'docs_count', '@EMSCore/release/columns/revisions.html.twig'))->setCellClass('text-right');
    }
}
