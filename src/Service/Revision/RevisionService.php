<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Common\DocumentInfo;
use EMS\CoreBundle\Contracts\Revision\RevisionServiceInterface;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\Core\Revision\Revisions;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\PublishService;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class RevisionService implements RevisionServiceInterface
{
    private DataService $dataService;
    private LoggerInterface $logger;
    private LoggerInterface $auditLogger;
    private RevisionRepository $revisionRepository;
    private PublishService $publishService;

    public function __construct(
        DataService $dataService,
        LoggerInterface $logger,
        LoggerInterface $auditLogger,
        RevisionRepository $revisionRepository,
        PublishService $publishService
    ) {
        $this->dataService = $dataService;
        $this->logger = $logger;
        $this->auditLogger = $auditLogger;
        $this->revisionRepository = $revisionRepository;
        $this->publishService = $publishService;
    }

    public function archive(Revision $revision, string $archivedBy, bool $flush = true): bool
    {
        $this->publishService->silentUnpublish($revision, $flush);

        $revision
            ->setArchived(true)
            ->setArchivedBy($archivedBy);

        if ($flush) {
            $this->revisionRepository->save($revision);
        }

        return true;
    }

    /**
     * @return ?array<mixed>
     */
    public function compare(Revision $revision, int $compareRevisionId): ?array
    {
        $logContext = [
            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            'compare_revision_id' => $compareRevisionId,
        ];

        try {
            $compareRevision = $this->revisionRepository->findOneById($compareRevisionId);
        } catch (\Throwable $e) {
            $this->logger->warning('log.data.revision.compare_revision_not_found', $logContext);

            return null;
        }

        if ($revision->giveContentType() === $compareRevision->giveContentType()
            && $revision->getOuuid() == $compareRevision->getOuuid()) {
            if ($compareRevision->getCreated() <= $revision->getCreated()) {
                $this->logger->notice('log.data.revision.compare', $logContext);
            } else {
                $this->logger->warning('log.data.revision.compare_more_recent', $logContext);
            }
        } else {
            $this->logger->notice('log.data.document.compare', \array_merge($logContext, [
                'compare_contenttype' => $compareRevision->giveContentType()->getName(),
                'compare_ouuid' => $compareRevision->getOuuid(),
            ]));
        }

        return $compareRevision->getRawData();
    }

    public function deleteByContentType(ContentType $contentType): int
    {
        return $this->revisionRepository->deleteByContentType($contentType);
    }

    public function deleteOldest(ContentType $contentType): int
    {
        return $this->revisionRepository->deleteOldest($contentType);
    }

    public function find(int $revisionId): ?Revision
    {
        $revision = $this->revisionRepository->find($revisionId);

        return $revision instanceof Revision ? $revision : null;
    }

    public function findByIdOrOuuid(ContentType $contentType, int $revisionId, string $ouuid): ?Revision
    {
        if ($revisionId > 0) {
            return $this->revisionRepository->findOneBy([
                'id' => $revisionId,
                'ouuid' => $ouuid,
                'deleted' => false,
            ]);
        }

        return $this->revisionRepository->findOneBy([
            'endTime' => null,
            'ouuid' => $ouuid,
            'deleted' => false,
            'contentType' => $contentType,
        ]);
    }

    /**
     * @return iterable|Revision[]
     */
    public function findAllDraftsByContentTypeName(string $contentTypeName): iterable
    {
        return $this->revisionRepository->findAllDraftsByContentTypeName($contentTypeName);
    }

    public function get(string $ouuid, string $contentType, ?\DateTimeInterface $dateTime = null): ?Revision
    {
        return $this->revisionRepository->findRevision($ouuid, $contentType, $dateTime);
    }

    public function getByEmsLink(EMSLink $emsLink, ?\DateTimeInterface $dateTime = null): ?Revision
    {
        return $this->get($emsLink->getOuuid(), $emsLink->getContentType(), $dateTime);
    }

    public function getCurrentRevisionForDocument(DocumentInterface $document): ?Revision
    {
        return $this->get($document->getId(), $document->getContentType());
    }

    public function getCurrentRevisionForEnvironment(string $ouuid, ContentType $contentType, Environment $environment): ?Revision
    {
        return $this->revisionRepository->findByEnvironment($ouuid, $contentType, $environment);
    }

    public function getCurrentRevisionByOuuidAndContentType(string $ouuid, string $contentType): ?Revision
    {
        return $this->get($ouuid, $contentType);
    }

    /**
     * @param array<mixed> $search
     */
    public function search(array $search): Revisions
    {
        return new Revisions($this->revisionRepository->search($search));
    }

    /**
     * @param array<mixed> $rawData
     */
    public function save(Revision $revision, array $rawData): void
    {
        if ($revision->getDraft()) {
            $revision->setDraftSaveDate(new \DateTime());
        } else {
            $revision->setDraftSaveDate(null);
        }
        $revision->setRawData($rawData);
        $this->dataService->setMetaFields($revision);

        $this->logger->debug('Revision before persist');
        $this->revisionRepository->save($revision);

        $this->auditLogger->info('log.revision.draft.updated', LogRevisionContext::update($revision));

        $this->logger->debug('Revision after persist flush');
    }

    public function getDocumentInfo(EMSLink $documentLink): DocumentInfo
    {
        return new DocumentInfo($documentLink, $this->revisionRepository->findAllPublishedRevision($documentLink));
    }

    /**
     * @param array<mixed> $rawData
     */
    public function create(ContentType $contentType, ?UuidInterface $uuid = null, array $rawData = [], ?string $username = null): Revision
    {
        return $this->dataService->newDocument($contentType, null === $uuid ? null : $uuid->toString(), $rawData, $username);
    }

    /**
     * @param ?array<mixed> $mergeRawData
     */
    public function copy(Revision $revision, ?array $mergeRawData = null): void
    {
        $copiedRevision = $revision->clone();

        if ($mergeRawData) {
            $copiedRevision->setRawData(\array_merge($copiedRevision->getRawData(), $mergeRawData));
        }

        $form = null;

        $this->dataService->finalizeDraft($copiedRevision, $form, 'system_copy');
    }

    /**
     * @param array<mixed> $rawData
     */
    public function updateRawData(Revision $revision, array $rawData, ?string $username = null, bool $merge = true): Revision
    {
        $contentTypeName = $revision->giveContentType()->getName();
        if ($revision->getDraft()) {
            $draft = $revision;
        } else {
            $draft = $this->dataService->initNewDraft($contentTypeName, $revision->giveOuuid(), null, $username);
        }

        $this->setRawData($draft, $rawData, $merge);
        $form = null;

        return $this->dataService->finalizeDraft($draft, $form, $username);
    }

    /**
     * @param array<mixed> $rawData
     */
    public function updateRawDataByEmsLink(EMSLink $emsLink, array $rawData, bool $merge = true): Revision
    {
        $draft = $this->dataService->initNewDraft($emsLink->getContentType(), $emsLink->getOuuid());

        $this->setRawData($draft, $rawData, $merge);

        return $this->dataService->finalizeDraft($draft);
    }

    /**
     * @param array<mixed> $rawData
     */
    private function setRawData(Revision $draft, array $rawData, bool $merge = true): void
    {
        if ($merge) {
            $draft->setRawData(\array_merge($draft->getRawData(), $rawData));
        } else {
            $draft->setRawData($rawData);
        }
    }

    public function getByRevisionId(string $revisionId): Revision
    {
        $revision = $this->revisionRepository->find($revisionId);
        if (!$revision instanceof Revision) {
            throw new \RuntimeException(\sprintf('Unexpected no Revision object for id: %s', $revisionId));
        }

        return $revision;
    }
}
