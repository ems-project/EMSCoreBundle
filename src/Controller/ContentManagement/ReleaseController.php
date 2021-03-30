<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\ReleaseType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Service\ReleaseRevisionService;
use EMS\CoreBundle\Service\ReleaseService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReleaseController extends AbstractController
{
    /** @var LoggerInterface */
    private $logger;
    /** @var ReleaseService */
    private $releaseService;
    /** @var ReleaseRevisionService */
    private $releaseRevisionService;

    public function __construct(LoggerInterface $logger, ReleaseService $releaseService, ReleaseRevisionService $releaseRevisionService)
    {
        $this->logger = $logger;
        $this->releaseService = $releaseService;
        $this->releaseRevisionService = $releaseRevisionService;
    }

    public function index(Request $request): Response
    {
        $table = new EntityTable($this->releaseService);
        $labelColumn = $table->addColumn('release.index.column.name', 'name');
        $labelColumn->setRouteProperty('defaultRoute');
        $labelColumn->setRouteTarget('release_%value%');
        $dateColumn = $table->addColumn('release.index.column.executionDate', 'executionDate');
        $dateColumn->setDateTimeProperty(true);
        $table->addColumn('release.index.column.status', 'status');
        $table->addItemGetAction('ems_core_release_edit', 'release.actions.edit', 'pencil');
        $table->addItemGetAction('ems_core_release_add_revisions', 'release.actions.add.revisions', 'plus');
        $table->addItemPostAction('ems_core_release_delete', 'release.actions.delete', 'trash', 'release.actions.delete_confirm');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'release.actions.delete_selected', 'release.actions.delete_selected_confirm');

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
        $form = $this->createForm(ReleaseType::class, $release);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->releaseService->update($release);

            return $this->redirectToRoute('ems_core_release_index');
        }

        return $this->render($view, [
            'form' => $form->createView(),
        ]);
    }

    public function delete(Release $release): Response
    {
        $this->releaseService->delete($release);

        return $this->redirectToRoute('ems_core_release_index');
    }

    public function addRevisions(Request $request, Release $release): Response
    {
        $table = new EntityTable($this->releaseRevisionService);
        $labelColumn = $table->addColumn('release.revision.index.column.label', 'labelField');
        $labelColumn->setRouteProperty('defaultRoute');
        $labelColumn->setRouteTarget('revision_%value%');
        $table->addColumn('release.revision.index.column.ouuid', 'ouuid');
        $table->addColumn('release.revision.index.column.CT', 'contentTypeName');
        $table->addColumn('release.revision.index.column.finalizedBy', 'finalizedBy');
        $dateColumn = $table->addColumn('release.revision.index.column.finalizeDate', 'finalizedDate');
        $dateColumn->setDateTimeProperty(true);
        $labelColumn->setRouteProperty('defaultRoute');
        $labelColumn->setRouteTarget('revision_%value%');
        $table->addTableAction(TableAbstract::SELECT_ACTION, 'fa fa-check', 'release.revision.actions.add_selected', 'release.revision.actions.add_selected_confirm');
        $table->setSelected($release->getRevisionsIds());

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::SELECT_ACTION:
                        $this->releaseService->updateRevisions($release, $table->getSelected());
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
}
