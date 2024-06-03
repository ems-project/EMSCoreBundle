<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\Revision\RevisionTrashDataTableType;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\DataService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Button;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TrashController extends AbstractController
{
    public function __construct(
        private readonly DataService $dataService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LoggerInterface $logger,
        private readonly string $templateNamespace
    ) {
    }

    public function trash(Request $request, ContentType $contentType): Response
    {
        if (!$this->isGranted($contentType->role(ContentTypeRoles::TRASH))) {
            throw $this->createAccessDeniedException();
        }

        $table = $this->dataTableFactory->create(RevisionTrashDataTableType::class, [
            'roles' => [$contentType->role(ContentTypeRoles::TRASH)],
            'content_type_name' => $contentType->getName(),
        ]);
        $form = $this->createForm(TableType::class, $table);

        $form->handleRequest($request);
        if ($form instanceof Form && $form->isSubmitted() && $form->isValid()) {
            $action = $form->getClickedButton() instanceof Button ? $form->getClickedButton()->getName() : null;
            $selection = $table->getSelected();

            return match ($action) {
                RevisionTrashDataTableType::ACTION_PUT_BACK => $this->putBackSelection($contentType, ...$selection),
                RevisionTrashDataTableType::ACTION_EMPTY_TRASH => $this->emptyTrashSelection($contentType, ...$selection),
                default => (function () use ($contentType) {
                    $this->logger->error('log.controller.channel.unknown_action');

                    return $this->redirectToRoute(Routes::DATA_TRASH, ['contentType' => $contentType->getId()]);
                })()
            };
        }

        return $this->render("@$this->templateNamespace/data/trash.html.twig", [
            'contentType' => $contentType,
            'revisions' => $this->dataService->getAllDeleted($contentType),
            'form' => $form->createView(),
        ]);
    }

    public function emptyTrash(ContentType $contentType, string $ouuid): RedirectResponse
    {
        if (!$this->isGranted($contentType->role(ContentTypeRoles::TRASH))) {
            throw $this->createAccessDeniedException();
        }

        $this->dataService->trashEmpty($contentType, $ouuid);

        return $this->redirectToRoute(Routes::DATA_TRASH, ['contentType' => $contentType->getId()]);
    }

    public function putBack(ContentType $contentType, string $ouuid): RedirectResponse
    {
        if (!$this->isGranted($contentType->role(ContentTypeRoles::CREATE))) {
            throw $this->createAccessDeniedException();
        }

        $revisionId = $this->dataService->trashPutBack($contentType, $ouuid);

        return $this->redirectToRoute(Routes::EDIT_REVISION, ['revisionId' => $revisionId]);
    }

    private function emptyTrashSelection(ContentType $contentType, string ...$ouuids): Response
    {
        if (!$this->isGranted($contentType->role(ContentTypeRoles::TRASH))) {
            throw $this->createAccessDeniedException();
        }

        $this->dataService->trashEmpty($contentType, ...$ouuids);

        return $this->redirectToRoute(Routes::DATA_TRASH, ['contentType' => $contentType->getId()]);
    }

    private function putBackSelection(ContentType $contentType, string ...$ouuids): Response
    {
        if (!$this->isGranted($contentType->role(ContentTypeRoles::CREATE))) {
            throw $this->createAccessDeniedException();
        }

        $revisionId = $this->dataService->trashPutBack($contentType, ...$ouuids);

        if ($revisionId) {
            return $this->redirectToRoute(Routes::EDIT_REVISION, ['revisionId' => $revisionId]);
        }

        return $this->redirectToRoute(Routes::DRAFT_IN_PROGRESS, ['contentTypeId' => $contentType->getId()]);
    }
}
