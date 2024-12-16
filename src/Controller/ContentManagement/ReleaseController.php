<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Revision\Release\ReleaseRevisionType;
use EMS\CoreBundle\DataTable\Type\Release\ReleaseOverviewDataTableType;
use EMS\CoreBundle\DataTable\Type\Release\ReleasePickDataTableType;
use EMS\CoreBundle\DataTable\Type\Release\ReleaseRevisionDataTableType;
use EMS\CoreBundle\DataTable\Type\Release\ReleaseRevisionsPublishDataTableType;
use EMS\CoreBundle\DataTable\Type\Release\ReleaseRevisionsUnpublishDataTableType;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\ReleaseType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ReleaseService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReleaseController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ReleaseService $releaseService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace,
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(ReleaseOverviewDataTableType::class);
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->releaseService->deleteByIds($table->getSelected()),
                default => $this->logger->error('log.controller.release.unknown_action'),
            };

            return $this->redirectToRoute(Routes::RELEASE_INDEX);
        }

        return $this->render("@$this->templateNamespace/release/index.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function add(Request $request): Response
    {
        $release = new Release();
        $form = $this->createForm(ReleaseType::class, $release, ['add' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $release = $this->releaseService->add($release);

            return $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $release->getId()]);
        }

        return $this->render("@$this->templateNamespace/release/add.html.twig", [
            'form_release' => $form->createView(),
        ]);
    }

    public function edit(Request $request, Release $release): Response
    {
        $revisionsTable = $this->dataTableFactory->create(ReleaseRevisionDataTableType::class, [
            'release_id' => $release->getId(),
        ]);

        $revisionsForm = $this->createForm(TableType::class, $revisionsTable, [
            'title_label' => 'release.revision.view.title',
        ]);

        $revisionsForm->handleRequest($request);
        if ($revisionsForm->isSubmitted() && $revisionsForm->isValid()) {
            match ($this->getClickedButtonName($revisionsForm)) {
                TableAbstract::REMOVE_ACTION => $this->releaseService->removeRevisions($release, $revisionsTable->getSelected()),
                default => $this->logger->error('log.controller.release.unknown_action'),
            };

            return $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $release->getId()]);
        }

        $releaseForm = $this->createForm(ReleaseType::class, $release);
        $releaseForm->handleRequest($request);
        if ($releaseForm->isSubmitted() && $releaseForm->isValid()) {
            $this->releaseService->update($release);

            return match ($this->getClickedButtonName($releaseForm)) {
                ReleaseType::BTN_SAVE_CLOSE => $this->redirectToRoute(Routes::RELEASE_INDEX, ['release' => $release->getId()]),
                default => $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $release->getId()]),
            };
        }

        return $this->render("@$this->templateNamespace/release/edit.html.twig", [
            'form' => $revisionsForm->createView(),
            'form_release' => $releaseForm->createView(),
            'release' => $release,
        ]);
    }

    public function view(Request $request, Release $release): Response
    {
        $table = $this->dataTableFactory->create(ReleaseRevisionDataTableType::class, [
            'release_id' => $release->getId(),
        ]);

        $form = $this->createForm(TableType::class, $table, [
            'title_label' => 'release.revision.view.title',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (ReleaseRevisionDataTableType::ACTION_ROLLBACK === $this->getClickedButtonName($form)) {
                $rollback = $this->releaseService->rollback($release, $table->getSelected());

                return $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $rollback->getId()]);
            }

            $this->logger->error('log.controller.release.unknown_action');

            return $this->redirectToRoute(Routes::RELEASE_VIEW, ['release' => $release->getId()]);
        }

        return $this->render("@$this->templateNamespace/release/view.html.twig", [
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

    public function addRevisionById(Release $release, Revision $revision, string $type): Response
    {
        match ($type) {
            'publish' => $this->releaseService->addRevisionForPublish($release, $revision),
            'unpublish' => $this->releaseService->addRevisionForUnpublish($release, $revision),
            default => throw new \RuntimeException('invalid type'),
        };

        return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
            'type' => $revision->giveContentType()->getName(),
            'ouuid' => $revision->getOuuid(),
            'revisionId' => $revision->getId(),
        ]);
    }

    public function addRevision(Release $release, string $type, string $emsLinkToAdd): Response
    {
        $releaseType = ReleaseRevisionType::from($type);
        $this->releaseService->addRevisions($release, $releaseType, [$emsLinkToAdd]);

        return $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $release->getId()]);
    }

    public function addRevisions(Request $request, Release $release, string $type): Response
    {
        $releaseType = ReleaseRevisionType::from($type);

        $tableType = match ($releaseType) {
            ReleaseRevisionType::PUBLISH => ReleaseRevisionsPublishDataTableType::class,
            ReleaseRevisionType::UNPUBLISH => ReleaseRevisionsUnpublishDataTableType::class,
        };
        $table = $this->dataTableFactory->create($tableType, ['release_id' => $release->getId()]);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (TableAbstract::ADD_ACTION === $this->getClickedButtonName($form)) {
                $this->releaseService->addRevisions($release, $releaseType, $table->getSelected());
            } else {
                $this->logger->error('log.controller.release.unknown_action');
            }

            return $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $release->getId()]);
        }

        return $this->render("@$this->templateNamespace/release/revisions.html.twig", [
            'form' => $form->createView(),
            'type' => $releaseType->value,
        ]);
    }

    public function releasePublish(Release $release): Response
    {
        $this->releaseService->executeRelease($release);

        return $this->redirectToRoute(Routes::RELEASE_INDEX);
    }

    public function pickRelease(Revision $revision): Response
    {
        $table = $this->dataTableFactory->create(ReleasePickDataTableType::class, [
            'revision_id' => $revision->getId(),
        ]);

        $form = $this->createForm(TableType::class, $table);

        return $this->render("@$this->templateNamespace/release/add-to-release.html.twig", [
            'form' => $form->createView(),
            'revision' => $revision,
        ]);
    }
}
