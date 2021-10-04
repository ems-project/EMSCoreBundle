<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Common\DocumentInfo;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Ramsey\Uuid\UuidInterface;
use Twig\Extension\RuntimeExtensionInterface;

class RevisionRuntime implements RuntimeExtensionInterface
{
    private RevisionService $revisionService;

    public function __construct(RevisionService $revisionService)
    {
        $this->revisionService = $revisionService;
    }

    public function getRevision(string $ouuid, string $contentTypeName): ?Revision
    {
        return $this->revisionService->getCurrentRevisionByOuuidAndContentType($ouuid, $contentTypeName);
    }

    public function getRevisionId(string $ouuid, string $contentTypeName): ?int
    {
        $revision = $this->revisionService->getCurrentRevisionByOuuidAndContentType($ouuid, $contentTypeName);

        return $revision ? $revision->getId() : null;
    }

    /**
     * @param array<mixed> $rawData
     */
    public function createRevision(ContentType $contentType, UuidInterface $uuid, array $rawData = []): Revision
    {
        return $this->revisionService->create($contentType, $uuid, $rawData);
    }

    /**
     * @param array<mixed> $rawData
     */
    public function updateRevision(string $emsLink, array $rawData): Revision
    {
        return $this->revisionService->updateRawDataByEmsLink(EMSLink::fromText($emsLink), $rawData, false);
    }

    /**
     * @param array<mixed> $rawData
     */
    public function mergeRevision(string $emsLink, array $rawData): Revision
    {
        return $this->revisionService->updateRawDataByEmsLink(EMSLink::fromText($emsLink), $rawData);
    }

    /**
     * @return iterable|Revision[]
     */
    public function getRevisionsInDraft(string $contentTypeName): iterable
    {
        return $this->revisionService->findAllDraftsByContentTypeName($contentTypeName);
    }

    /**
     * @param string|EMSLink $documentLink
     */
    public function getDocumentInfo($documentLink): ?DocumentInfo
    {
        try {
            if (\is_string($documentLink)) {
                $documentLink = EMSLink::fromText($documentLink);
            }

            return $this->revisionService->getDocumentInfo($documentLink);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
