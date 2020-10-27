<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\DataService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;

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

    public function getCurrentRevisionForDocument(DocumentInterface $document): ?Revision
    {
        return $this->revisionRepository->findCurrentByOuuidAndContentTypeName(
            $document->getId(),
            $document->getContentType()
        );
    }

    public function getCurrentRevisionByOuuidAndContentType(string $ouuid, string $contentType): ?Revision
    {
        return $this->revisionRepository->findCurrentByOuuidAndContentTypeName($ouuid, $contentType);
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
    public function saveVersion(Revision $revision, array $rawData, string $version = null): Revision
    {
        if (!$revision->hasVersionTags() || $version === null) { //silent publish
            $this->save($revision, $rawData);
            $this->dataService->finalizeDraft($revision);
            return $revision;
        }

        $newVersion = $revision->clone();
        $this->dataService->lockRevision($newVersion);

        $this->save($newVersion, $rawData);
        $this->dataService->finalizeDraft($newVersion);

        $this->dataService->discardDraft($revision); //discard draft previous version

        return $newVersion;
    }
}
