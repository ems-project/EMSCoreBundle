<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Context\DocumentImportContext;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\RevisionRepository;
use Symfony\Component\Form\FormFactoryInterface;

class DocumentService
{
    private ?EntityManager $entityManager = null;

    public function __construct(private readonly Registry $doctrine, protected DataService $dataService, private readonly FormFactoryInterface $formFactory, private readonly Bulker $bulker, private readonly RevisionRepository $revisionRepository)
    {
    }

    public function initDocumentImporterContext(ContentType $contentType, string $lockUser, bool $rawImport, bool $signData, bool $indexInDefaultEnv, int $bulkSize, bool $finalize, bool $force): DocumentImportContext
    {
        $this->bulker->setSign($signData);
        $this->bulker->setSize($bulkSize);

        return new DocumentImportContext($contentType, $lockUser, $rawImport, $indexInDefaultEnv, $finalize, $force);
    }

    public function flushAndSend(DocumentImportContext $documentImportContext): void
    {
        $this->getEntityManager()->flush();
        if ($documentImportContext->shouldFinalize()) {
            $this->bulker->send(true);
        }
    }

    private function submitData(DocumentImportContext $documentImportContext, Revision $revision, ?Revision $previousRevision = null): void
    {
        $revisionType = $this->formFactory->create(RevisionType::class, $revision, ['migration' => true, 'with_warning' => false, 'raw_data' => $revision->getRawData()]);
        $viewData = $this->dataService->getSubmitData($revisionType->get('data'));

        $revisionType->setData($previousRevision);

        $revisionType->submit(['data' => $viewData]);
        $data = $revisionType->get('data')->getData();
        $revision->setData($data);
        $objectArray = $revision->getRawData();

        $this->dataService->propagateDataToComputedField($revisionType->get('data'), $objectArray, $documentImportContext->getContentType(), $documentImportContext->getContentType()->getName(), $revision->getOuuid(), true);
        $revision->setRawData($objectArray);

        unset($revisionType);
    }

    /**
     * @param array<mixed>                                                   $rawData
     * @param array{'creation_date'?: ?\DateTime, 'start_time'?: ?\DateTime} $options
     */
    public function importDocument(DocumentImportContext $documentImportContext, string $ouuid, array $rawData, array $options = []): void
    {
        $newRevision = $this->dataService->getEmptyRevision($documentImportContext->getContentType(), $documentImportContext->getLockUser());
        if (!$documentImportContext->shouldFinalize()) {
            $newRevision->removeEnvironment($documentImportContext->getEnvironment());
        }
        $newRevision->setOuuid($ouuid);
        $newRevision->setRawData($rawData);
        $newRevision->setCreated($options['creation_date'] ?? $newRevision->getCreated());
        $newRevision->setStartTime($options['start_time'] ?? $newRevision->getStartTime());
        $newRevision->setVersionMetaFields();

        $currentRevision = $this->revisionRepository->getCurrentRevision($documentImportContext->getContentType(), $ouuid);

        if ($currentRevision && $currentRevision->getDraft()) {
            if (!$documentImportContext->shouldForce()) {
                // TODO: activate the newRevision when it's available
                throw new CantBeFinalizedException('a draft is already in progress for the document', 0, null/* , $newRevision */);
            }

            $this->dataService->discardDraft($currentRevision, true, $documentImportContext->getLockUser());
            $currentRevision = $this->revisionRepository->getCurrentRevision($documentImportContext->getContentType(), $ouuid);
        }
        if (!$documentImportContext->shouldRawImport()) {
            $this->submitData($documentImportContext, $newRevision, $currentRevision ?? $this->dataService->getEmptyRevision($documentImportContext->getContentType(), $documentImportContext->getLockUser()));
            $this->dataService->sign($newRevision);
        }

        if ($currentRevision) {
            $currentRevision->setLockBy($documentImportContext->getLockUser());
            $currentRevision->setLockUntil((new \DateTime('now'))->add(new \DateInterval('PT5M')));

            if ($documentImportContext->shouldOnlyChanged() && $currentRevision->hasHash() && $currentRevision->getHash() === $newRevision->getHash()) {
                $this->getEntityManager()->persist($currentRevision); // updateModified
                $this->getEntityManager()->flush();

                return;
            }

            $currentRevision->setEndTime($newRevision->getStartTime());
            $currentRevision->setDraft(false);
            $currentRevision->autoSaveClear();
            if ($documentImportContext->shouldFinalize()) {
                $currentRevision->removeEnvironment($documentImportContext->getEnvironment());
            }
            $this->getEntityManager()->persist($currentRevision);
        }

        $this->dataService->setMetaFields($newRevision);

        if ($documentImportContext->shouldIndexInDefaultEnv() && $documentImportContext->shouldFinalize()) {
            $body = $newRevision->getRawData();

            $this->bulker->index($documentImportContext->getContentType()->getName(), $ouuid, $documentImportContext->getEnvironment()->getAlias(), $body);
        }

        $newRevision->setDraft(!$documentImportContext->shouldFinalize());
        $this->getEntityManager()->persist($newRevision);
        $this->revisionRepository->finaliseRevision($documentImportContext->getContentType(), $ouuid, $newRevision->getStartTime(), $documentImportContext->getLockUser());
        $this->revisionRepository->publishRevision($newRevision, $newRevision->getDraft());
    }

    private function getEntityManager(): EntityManager
    {
        if (null !== $this->entityManager) {
            return $this->entityManager;
        }

        $manager = $this->doctrine->getManager();
        if (!$manager instanceof EntityManager) {
            throw new \Exception('Can not get the Entity Manager');
        }
        $this->entityManager = $manager;

        return $this->entityManager;
    }
}
