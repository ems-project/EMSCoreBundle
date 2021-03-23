<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\DataService;
use Psr\Log\LoggerInterface;

class RevisionService
{
    /** @var DataService */
    private $dataService;
    /** @var LoggerInterface */
    private $logger;
    /** @var RevisionRepository */
    private $revisionRepository;

    public function __construct(
        DataService $dataService,
        LoggerInterface $logger,
        RevisionRepository $revisionRepository
    ) {
        $this->dataService = $dataService;
        $this->logger = $logger;
        $this->revisionRepository = $revisionRepository;
    }

    public function find(int $revisionId): ?Revision
    {
        $revision = $this->revisionRepository->find($revisionId);

        return $revision instanceof Revision ? $revision : null;
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

    public function getCurrentRevisionByOuuidAndContentType(string $ouuid, string $contentType): ?Revision
    {
        return $this->get($ouuid, $contentType);
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
        $previousVersion->setVersionDate('to', $now);
        $this->dataService->finalizeDraft($previousVersion);

        return $newVersion;
    }
}
