<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CoreBundle\Common\DocumentInfo;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Ramsey\Uuid\UuidInterface;
use Twig\Extension\RuntimeExtensionInterface;

class RevisionRuntime implements RuntimeExtensionInterface
{
    public function __construct(private readonly RevisionService $revisionService)
    {
    }

    public function display(Revision|Document|string $display, ?string $expression = null): string
    {
        return $this->revisionService->display($display, $expression);
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

    public function getDocumentInfo(string|EMSLink $documentLink): ?DocumentInfo
    {
        try {
            if (\is_string($documentLink)) {
                $documentLink = EMSLink::fromText($documentLink);
            }

            return $this->revisionService->getDocumentInfo($documentLink);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param string[] $documentLinks
     *
     * @return DocumentInfo[]
     */
    public function getDocumentsInfo(array $documentLinks): array
    {
        $documentLinks = \array_map(static fn (string $documentLink) => EMSLink::fromText($documentLink), $documentLinks);

        return $this->revisionService->getDocumentsInfo(...$documentLinks);
    }
}
