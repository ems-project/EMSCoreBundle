<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\NonUniqueResultException;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\ReleaseType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\ReleaseRevisionService;
use EMS\CoreBundle\Service\ReleaseService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ReleaseController extends AbstractController
{
    /** @var LoggerInterface */
    private $logger;
    /** @var ReleaseService */
    private $releaseService;
    /** @var ReleaseRevisionService */
    private $releaseRevisionService;
    /** @var */
    private $publishService;

    public function __construct(LoggerInterface $logger, ReleaseService $releaseService, ReleaseRevisionService $releaseRevisionService, PublishService $publishService)
    {
        $this->logger = $logger;
        $this->releaseService = $releaseService;
        $this->releaseRevisionService = $releaseRevisionService;
        $this->publishService = $publishService;
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
        $release = new Release();
        $release->setName('');
        $release->setStatus('WIP');

        return $this->edit($request, $release, '@EMSCore/release/add.html.twig');
    }

    public function edit(Request $request, Release $release, string $view = '@EMSCore/release/edit.html.twig'): Response
    {
        $table = new EntityTable($this->releaseRevisionService, $this->generateUrl('ems_core_release_ajax_data_table'), ['option' => TableAbstract::REMOVE_ACTION, 'selected' => $release->getRevisionsIds()]);
        $table->addColumn('release.revision.index.column.label', 'labelField');
        $table->addColumn('release.revision.index.column.CT', 'contentTypeName');
        $table->addColumn('release.revision.index.column.finalizedBy', 'finalizedBy');
        $table->addColumnDefinition(new DatetimeTableColumn('release.revision.index.column.finalizeDate', 'finalizedDate'));

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
            'form' => $form->createView(),
            'form_release' => $form2->createView(),
        ]);
    }

    public function delete(Release $release): Response
    {
        $this->releaseService->delete($release);

        return $this->redirectToRoute('ems_core_release_index');
    }

    public function addRevisions(Request $request, Release $release): Response
    {
        $table = new EntityTable($this->releaseRevisionService, $this->generateUrl('ems_core_release_ajax_data_table'), ['option' => TableAbstract::ADD_ACTION, 'selected' => $release->getRevisionsIds()]);
        $labelColumn = $table->addColumn('release.revision.index.column.label', 'labelField');
//         $labelColumn->setRouteProperty('defaultRoute');
//         $labelColumn->setRouteTarget('revision_%value%');
        $table->addColumn('release.revision.index.column.CT', 'contentTypeName');
        $table->addColumn('release.revision.index.column.finalizedBy', 'finalizedBy');
        $table->addColumnDefinition(new DatetimeTableColumn('release.revision.index.column.finalizeDate', 'finalizedDate'));
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
        /** @var Revision $revision */
        foreach ($release->getRevisions() as $revision) {
            //dump('revision ' . $revision->getLabel());
            /** @var Environment $env */
            foreach ($release->getEnvironments() as $env) {
                // dump('env ' . $env->getName());
                // if already env in revision => nothing happend
                //dump($revision->getEnvironments()->count());
                if ($revision->getEnvironments()->count() > 0) {
                    if ($revision->getEnvironments()->contains($env)) {
                        //      dump('already env in revision nothing happend');
                        continue;
                    }
                }

                //dump('need to publish on env ' . $env->getName());

                $contentType = $revision->getContentType();
                if (null === $contentType) {
                    throw new RuntimeException('Content type not found');
                }
                if ($contentType->getDeleted()) {
                    throw new RuntimeException('Content type deleted');
                }

                try {
                    $this->publishService->publish($revision, $env);
                } catch (NonUniqueResultException $e) {
                    throw new NotFoundHttpException('Revision not found');
                }
            }
        }

        return $this->redirectToRoute('ems_core_release_index');
    }

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->releaseService, $this->generateUrl('ems_core_release_ajax_data_table'));
        $table->addColumn('release.index.column.name', 'name');
        $table->addColumnDefinition(new DatetimeTableColumn('release.index.column.executionDate', 'executionDate'));
        $table->addColumn('release.index.column.status', 'status');
        $table->addItemGetAction('ems_core_release_edit', 'release.actions.edit', 'pencil');
        $table->addItemGetAction('ems_core_release_add_revisions', 'release.actions.add.revisions', 'plus');
        $table->addItemPostAction('ems_core_release_delete', 'release.actions.delete', 'trash', 'release.actions.delete_confirm');
        $table->addItemPostAction('ems_core_release_publish', 'release.actions.publish', 'toggle-on', 'release.actions.publish_confirm');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'release.actions.delete_selected', 'release.actions.delete_selected_confirm');

        return $table;
    }
}
