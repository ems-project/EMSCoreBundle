<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Core\DataTable\DataTableFactory;
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
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReleaseController extends AbstractController
{
    public const ROLLBACK_ACTION = 'rollback_action';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ReleaseService $releaseService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(ReleaseOverviewDataTableType::class);
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                match ($action->getName()) {
                    TableAbstract::DELETE_ACTION => $this->releaseService->deleteByIds($table->getSelected()),
                    default => $this->logger->error('log.controller.release.unknown_action'),
                };
            } else {
                $this->logger->error('log.controller.release.unknown_action');
            }

            return $this->redirectToRoute(Routes::RELEASE_INDEX);
        }

        return $this->render("@$this->templateNamespace/release/index.html.twig", [
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
            if ($revisionsForm instanceof Form && ($action = $revisionsForm->getClickedButton()) instanceof SubmitButton) {
                match ($action->getName()) {
                    TableAbstract::REMOVE_ACTION => $this->releaseService->removeRevisions($release, $revisionsTable->getSelected()),
                    default => $this->logger->error('log.controller.release.unknown_action'),
                };
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
            default => throw new \RuntimeException('invalid type')
        };

        return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
            'type' => $revision->giveContentType()->getName(),
            'ouuid' => $revision->getOuuid(),
            'revisionId' => $revision->getId(),
        ]);
    }

    public function addRevision(Release $release, string $type, string $emsLinkToAdd): Response
    {
        $this->releaseService->addRevisions($release, $type, [$emsLinkToAdd]);

        return $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $release->getId()]);
    }

    public function addRevisions(Request $request, Release $release, string $type): Response
    {
        $tableType = match ($type) {
            'publish' => ReleaseRevisionsPublishDataTableType::class,
            'unpublish' => ReleaseRevisionsUnpublishDataTableType::class,
            default => throw new \RuntimeException('invalid type')
        };
        $table = $this->dataTableFactory->create($tableType, ['release_id' => $release->getId()]);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                match ($action->getName()) {
                    TableAbstract::ADD_ACTION => $this->releaseService->addRevisions($release, $type, $table->getSelected()),
                    default => $this->logger->error('log.controller.release.unknown_action'),
                };
            } else {
                $this->logger->error('log.controller.release.unknown_action');
            }

            return $this->redirectToRoute(Routes::RELEASE_EDIT, ['release' => $release->getId()]);
        }

        return $this->render("@$this->templateNamespace/release/revisions.html.twig", [
            'form' => $form->createView(),
            'type' => $type,
        ]);
    }

    public function releasePublish(Release $release): Response
    {
        $this->releaseService->publishRelease($release);

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
