<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Common\DocumentInfo;
use EMS\CoreBundle\Contracts\Revision\RevisionServiceInterface;
use EMS\CoreBundle\Core\Revision\Revisions;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\PublishService;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class RevisionService implements RevisionServiceInterface
{
    private DataService $dataService;
    private LoggerInterface $logger;
    private RevisionRepository $revisionRepository;
    private PublishService $publishService;

    public function __construct(
        DataService $dataService,
        LoggerInterface $logger,
        RevisionRepository $revisionRepository,
        PublishService $publishService
    ) {
        $this->dataService = $dataService;
        $this->logger = $logger;
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

    public function find(int $revisionId): ?Revision
    {
        $revision = $this->revisionRepository->find($revisionId);

        return $revision instanceof Revision ? $revision : null;
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
        $revision->setRawData($rawData);
        $this->dataService->setMetaFields($revision);

        $this->logger->debug('Revision before persist');
        $this->revisionRepository->save($revision);
        $this->logger->debug('Revision after persist flush');
    }

    /**
     * @param array<mixed> $rawData
     */
    public function saveFinalize(Revision $revision, array $rawData): Revision
    {
        $this->save($revision, $rawData);
        $this->dataService->finalizeDraft($revision);

        return $revision;
    }

    /**
     * The revision is a draft, version meta fields set in Revision->setVersionMetaFields().
     *
     * @param array<mixed> $rawData
     */
    public function saveVersion(Revision $revision, array $rawData, ?string $versionTag = null): Revision
    {
        if (null !== $versionTag) {
            $revision->setVersionTag($versionTag); //update version_tag archived versions
        }

        if (null === $versionTag || null !== $revision->getVersionDate('to') || !$revision->hasOuuid()) {
            //silent version publish || changing archived version revision || new document draft
            return $this->saveFinalize($revision, $rawData);
        }

        $now = new \DateTimeImmutable();

        $newVersion = $revision->clone(); //create new version revision
        $this->dataService->lockRevision($newVersion);
        $newVersion->setVersionDate('from', $now);
        $this->dataService->finalizeDraft($newVersion);

        $this->dataService->discardDraft($revision); //discard draft changes previous revision

        $previousVersion = $this->dataService->initNewDraft($revision->getContentTypeName(), $revision->getOuuid());
        $previousVersion->clearTasks();
        $previousVersion->setVersionDate('to', $now);
        $this->dataService->finalizeDraft($previousVersion);

        return $newVersion;
    }

    public function getDocumentInfo(EMSLink $documentLink): DocumentInfo
    {
        return new DocumentInfo($documentLink, $this->revisionRepository->findAllPublishedRevision($documentLink));
    }

    public function generate(): Revision
    {
        $revision = new Revision();
        $revision->setOuuid(Uuid::uuid4()->toString());

        return $revision;
    }

    /**
     * @param array<mixed> $rawData
     */
    public function create(ContentType $contentType, UuidInterface $uuid, array $rawData = []): Revision
    {
        return $this->dataService->newDocument($contentType, $uuid->toString(), $rawData);
    }

    /**
     * @param array<mixed> $rawData
     */
    public function updateRawData(Revision $revision, array $rawData, ?string $username = null, bool $merge = true): Revision
    {
        $contentTypeName = $revision->giveContentType()->getName();
        $draft = $this->dataService->initNewDraft($contentTypeName, $revision->giveOuuid(), null, $username);

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
