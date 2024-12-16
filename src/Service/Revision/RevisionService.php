<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Contracts\ExpressionServiceInterface;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException as CommonNotFoundException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Common\DocumentInfo;
use EMS\CoreBundle\Contracts\Revision\RevisionServiceInterface;
use EMS\CoreBundle\Core\ContentType\ContentTypeFields;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\Core\Revision\Revisions;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\NotFoundException;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\PublishService;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class RevisionService implements RevisionServiceInterface
{
    public function __construct(
        private readonly DataService $dataService,
        private readonly FormFactory $formFactory,
        private readonly LoggerInterface $logger,
        private readonly LoggerInterface $auditLogger,
        private readonly RevisionRepository $revisionRepository,
        private readonly PublishService $publishService,
        private readonly ContentTypeService $contentTypeService,
        private readonly UserManager $userManager,
        private readonly ExpressionServiceInterface $expressionService,
        private readonly ElasticaService $elasticaService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function archive(Revision $revision, string $archivedBy, bool $flush = true): bool
    {
        $this->publishService->silentUnpublish($revision, $flush);

        $revision
            ->setArchived(true)
            ->setArchivedBy($archivedBy);

        if ($flush) {
            $this->revisionRepository->save($revision);
            $this->elasticaService->refresh($revision->giveContentType()->giveEnvironment()->getAlias());
        }

        return true;
    }

    /**
     * @return array<mixed>
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
        } catch (\Throwable) {
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

    public function createRevisionForm(Revision $revision, bool $ignoreNotConsumed = false): FormInterface
    {
        if (null == $revision->getDatafield()) {
            $this->dataService->loadDataStructure($revision, $ignoreNotConsumed);
        }

        return $this->formFactory->createBuilder(RevisionType::class, $revision, [
            'raw_data' => $revision->getRawData(),
        ])->getForm();
    }

    public function deleteByContentType(ContentType $contentType): int
    {
        return $this->revisionRepository->deleteByContentType($contentType);
    }

    /**
     * @param string[] $ouuids
     */
    public function deleteByOuuids(array $ouuids): int
    {
        return $this->revisionRepository->deleteByOuuids($ouuids);
    }

    public function deleteOldest(ContentType $contentType, ?string $ouuid): int
    {
        return $this->revisionRepository->deleteOldest($contentType, $ouuid);
    }

    public function display(Revision|DocumentInterface|string $value, ?string $expression = null): string
    {
        if (\is_string($value)) {
            if (null === $object = $this->resolveEmsLink(EMSLink::fromText($value))) {
                return $value;
            }
        } else {
            $object = $value;
        }

        $contentType = match (true) {
            ($object instanceof Revision) => $object->giveContentType(),
            ($object instanceof DocumentInterface) => $this->contentTypeService->giveByName($object->getContentType()),
        };

        $rawData = match (true) {
            ($object instanceof Revision) => $object->getRawData(),
            ($object instanceof DocumentInterface) => $object->getSource(),
        };

        $expression ??= $contentType->getFields()[ContentTypeFields::DISPLAY];
        $evaluateDisplay = $expression ? $this->expressionService->evaluateToString($expression, [
            'rawData' => $rawData,
            'userLocale' => $this->userManager->getUserLanguage(),
        ]) : null;

        if ($evaluateDisplay) {
            return $evaluateDisplay;
        }

        if ($contentType->hasLabelField() && isset($rawData[$contentType->giveLabelField()])) {
            return $rawData[$contentType->giveLabelField()];
        }

        return match (true) {
            ($object instanceof Revision && null === $object->getOuuid() && $object->getEnvironments()->isEmpty()) => t(
                message: 'revision.new',
                parameters: ['contentType' => $contentType->getSingularName()],
                domain: 'emsco-core'
            )->trans($this->translator),
            ($object instanceof Revision) => $object->giveOuuid(),
            ($object instanceof DocumentInterface) => $object->getId(),
        };
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
        return yield from $this->revisionRepository
            ->makeQueryBuilder(contentTypeName: $contentTypeName, isDraft: true)
            ->getQuery()
            ->toIterable();
    }

    public function give(string $ouuid, ?string $contentType = null, ?\DateTimeInterface $dateTime = null): Revision
    {
        if (null === $revision = $this->get($ouuid, $contentType, $dateTime)) {
            throw NotFoundException::revisionForOuuid($ouuid);
        }

        return $revision;
    }

    public function get(string $ouuid, ?string $contentType = null, ?\DateTimeInterface $dateTime = null): ?Revision
    {
        return $this->revisionRepository->findRevision($ouuid, $contentType, $dateTime);
    }

    public function getByEmsLink(EMSLink $emsLink, ?\DateTimeInterface $dateTime = null): ?Revision
    {
        if (!$emsLink->isValid()) {
            return null;
        }

        return $this->get($emsLink->getOuuid(), $emsLink->getContentType(), $dateTime);
    }

    private function resolveEmsLink(EMSLink $emsLink): Revision|DocumentInterface|null
    {
        if (!$emsLink->isValid()) {
            return null;
        }

        $contentType = $this->contentTypeService->giveByName($emsLink->getContentType());
        $environment = $contentType->giveEnvironment();

        if ($environment->getManaged()) {
            return $this->getByEmsLink($emsLink);
        }

        try {
            return $this->elasticaService->getDocument(
                index: $environment->getAlias(),
                contentType: $contentType->getName(),
                id: $emsLink->getOuuid()
            );
        } catch (CommonNotFoundException) {
            return null;
        }
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

    public function lock(Revision $revision, ?UserInterface $user = null, ?\DateTime $lockTime = null): Revision
    {
        $this->dataService->lockRevision(
            revision: $revision,
            username: $user?->getUserIdentifier(),
            lockTime: $lockTime
        );

        return $revision;
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
        $publishedRevisions = $this->revisionRepository->findAllPublishedRevision($documentLink);
        $revisions = $publishedRevisions[$documentLink->getEmsId()] ?? [];

        return new DocumentInfo($documentLink, $revisions);
    }

    /**
     * @return array<string, DocumentInfo>
     */
    public function getDocumentsInfo(EMSLink ...$documentLinks): array
    {
        $publishedRevisions = $this->revisionRepository->findAllPublishedRevision(...$documentLinks);

        $documentsInfo = [];
        foreach ($publishedRevisions as $emsId => $revisions) {
            $documentsInfo[$emsId] = new DocumentInfo(EMSLink::fromText($emsId), $revisions);
        }

        return $documentsInfo;
    }

    /**
     * @param array<mixed> $rawData
     */
    public function create(ContentType $contentType, ?UuidInterface $uuid = null, array $rawData = [], ?string $username = null): Revision
    {
        return $this->dataService->newDocument($contentType, null === $uuid ? null : $uuid->toString(), $rawData, $username);
    }

    /**
     * @param array<mixed> $mergeRawData
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
    public function updateRawDataByEmsLink(EMSLink $emsLink, array $rawData, bool $merge = true, ?string $username = null): Revision
    {
        $draft = $this->dataService->initNewDraft(
            type: $emsLink->getContentType(),
            ouuid: $emsLink->getOuuid(),
            username: $username
        );

        $this->setRawData($draft, $rawData, $merge);

        return $this->dataService->finalizeDraft(revision: $draft, username: $username);
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

    public function getByRevisionId(int|string $revisionId): Revision
    {
        $revision = $this->revisionRepository->find($revisionId);
        if (!$revision instanceof Revision) {
            throw new \RuntimeException(\sprintf('Unexpected no Revision object for id: %s', $revisionId));
        }

        return $revision;
    }
}
