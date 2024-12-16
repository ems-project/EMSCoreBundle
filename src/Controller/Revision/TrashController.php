<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\Revision\RevisionTrashDataTableType;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\DataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class TrashController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly DataService $dataService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LocalizedLoggerInterface $logger,
        private readonly string $templateNamespace,
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
        if ($form->isSubmitted() && $form->isValid()) {
            return match ($this->getClickedButtonName($form)) {
                RevisionTrashDataTableType::ACTION_PUT_BACK => $this->putBackSelection($contentType, ...$table->getSelected()),
                RevisionTrashDataTableType::ACTION_EMPTY_TRASH => $this->emptyTrashSelection($contentType, ...$table->getSelected()),
                default => (function () use ($contentType) {
                    $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core'));

                    return $this->redirectToRoute(Routes::DATA_TRASH, ['contentType' => $contentType->getId()]);
                })(),
            };
        }

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-trash',
            'title' => t('revision.trash.title', ['pluralName' => $contentType->getPluralName()], 'emsco-core'),
            'breadcrumb' => [
                'contentType' => $contentType,
                'page' => t('revision.trash.label', [], 'emsco-core'),
            ],
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

        $restoredRevision = $this->dataService->trashPutBackAsDraft($contentType, $ouuid);

        if (!$restoredRevision) {
            throw new \RuntimeException(\sprintf('Put back failed for ouuid "%s"', $ouuid));
        }

        return $this->redirectToRoute(Routes::EDIT_REVISION, ['revisionId' => $restoredRevision->getId()]);
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

        $restoredRevision = $this->dataService->trashPutBackAsDraft($contentType, ...$ouuids);

        if ($restoredRevision) {
            return $this->redirectToRoute(Routes::EDIT_REVISION, ['revisionId' => $restoredRevision->getId()]);
        }

        return $this->redirectToRoute(Routes::DRAFT_IN_PROGRESS, ['contentTypeId' => $contentType->getId()]);
    }
}
