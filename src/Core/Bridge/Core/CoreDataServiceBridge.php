<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Bridge\Core;

use EMS\CommonBundle\Contracts\Bridge\Core\CoreDataBridgeInterface;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;

readonly class CoreDataServiceBridge implements CoreDataBridgeInterface
{
    public function __construct(
        private ContentType $contentType,
        private DataService $dataService,
        private RevisionService $revisionService,
    ) {
    }

    #[\Override]
    public function autoSave(int $revisionId, array $data): bool
    {
        $revision = $this->revisionService->getByRevisionId($revisionId);
        $this->revisionService->autoSave($revision, $data);

        return true;
    }

    #[\Override]
    public function create(array $rawData = []): int
    {
        return $this->revisionService->create(contentType: $this->contentType, rawData: $rawData)->getId();
    }

    #[\Override]
    public function discard(int $revisionId): bool
    {
        $revision = $this->revisionService->getByRevisionId($revisionId);
        $this->dataService->discardDraft($revision);

        return !$revision->hasId();
    }

    #[\Override]
    public function getDraft(int $revisionId): array
    {
        $revision = $this->revisionService->getByRevisionId($revisionId);

        return [
            'id' => $revision->getId(),
            'data' => $revision->getDraftData(),
        ];
    }

    #[\Override]
    public function finalize(int $revisionId): string
    {
        $revision = $this->dataService->getRevisionById($revisionId, $this->contentType);
        $revision->autoSaveToRawData();
        $newRevision = $this->dataService->finalizeDraft($revision);

        $this->dataService->refresh($this->contentType->giveEnvironment());

        return $newRevision->giveOuuid();
    }
}
