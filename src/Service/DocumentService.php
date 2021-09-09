<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Context\DocumentImportContext;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Symfony\Component\Form\FormFactoryInterface;

class DocumentService
{
    /** @var DataService */
    protected $dataService;
    /** @var FormFactoryInterface */
    private $formFactory;
    /** @var Registry */
    private $doctrine;
    /** @var Bulker */
    private $bulker;
    /** @var string */
    private $instanceId;
    /** @var EntityManager */
    private $entityManager;
    /** @var RevisionRepository */
    private $revisionRepository;
    /** @var ContentTypeRepository */
    private $contentTypeRepository;

    public function __construct(Registry $doctrine, DataService $dataService, FormFactoryInterface $formFactory, Bulker $bulker, string $instanceId)
    {
        $this->dataService = $dataService;
        $this->formFactory = $formFactory;
        $this->doctrine = $doctrine;
        $this->bulker = $bulker;
        $this->instanceId = $instanceId;
    }

    public function initDocumentImporterContext(ContentType $contentType, string $lockUser, bool $rawImport, bool $signData, bool $indexInDefaultEnv, int $bulkSize, bool $finalize, bool $force): DocumentImportContext
    {
        $manager = $this->doctrine->getManager();
        if (!$manager instanceof EntityManager) {
            throw new \Exception('Can not get the Entity Manager');
        }
        $this->entityManager = $manager;
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);
        $this->bulker->setSign($signData);
        $this->bulker->setSize($bulkSize);

        $repository = $this->entityManager->getRepository('EMSCoreBundle:Revision');
        if (!$repository instanceof RevisionRepository) {
            throw new \Exception('Can not get the Revision Repository');
        }

        $this->revisionRepository = $repository;

        $repository = $this->entityManager->getRepository('EMSCoreBundle:ContentType');
        if (!$repository instanceof ContentTypeRepository) {
            throw new \Exception('Can not get the ContentType Repository');
        }
        $this->contentTypeRepository = $repository;

        return new DocumentImportContext($contentType, $lockUser, $rawImport, $indexInDefaultEnv, $finalize, $force);
    }

    public function flushAndSend(DocumentImportContext $documentImportContext)
    {
        $this->entityManager->flush();
        if ($documentImportContext->shouldFinalize()) {
            $this->bulker->send(true);
        }
    }

    private function submitData(DocumentImportContext $documentImportContext, Revision $revision, Revision $previousRevision = null)
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

    public function importDocument(DocumentImportContext $documentImportContext, string $ouuid, array $rawData)
    {
        $newRevision = $this->dataService->getEmptyRevision($documentImportContext->getContentType(), $documentImportContext->getLockUser());
        if (!$documentImportContext->shouldFinalize()) {
            $newRevision->removeEnvironment($documentImportContext->getEnvironment());
        }
        $newRevision->setOuuid($ouuid);
        $newRevision->setRawData($rawData);

        $currentRevision = $this->revisionRepository->getCurrentRevision($documentImportContext->getContentType(), $ouuid);

        if ($currentRevision && $currentRevision->getDraft()) {
            if (!$documentImportContext->shouldForce()) {
                //TODO: activate the newRevision when it's available
                throw new CantBeFinalizedException('a draft is already in progress for the document', 0, null/*, $newRevision*/);
            }

            $this->dataService->discardDraft($currentRevision, true, $documentImportContext->getLockUser());
            $currentRevision = $this->revisionRepository->getCurrentRevision($documentImportContext->getContentType(), $ouuid);
        }
        if (!$documentImportContext->shouldRawImport()) {
            $this->submitData($documentImportContext, $newRevision, $currentRevision ?? $this->dataService->getEmptyRevision($documentImportContext->getContentType(), $documentImportContext->getLockUser()));
        }

        if ($currentRevision) {
            $currentRevision->setLockBy($documentImportContext->getLockUser());
            $currentRevision->setLockUntil($newRevision->getLockUntil());

            if ($documentImportContext->shouldOnlyChanged() && $currentRevision->getHash() === $newRevision->getHash()) {
                $this->entityManager->persist($currentRevision); // updateModified
                $this->entityManager->flush();

                return;
            }

            $currentRevision->setEndTime($newRevision->getStartTime());
            $currentRevision->setDraft(false);
            $currentRevision->setAutoSave(null);
            if ($documentImportContext->shouldFinalize()) {
                $currentRevision->removeEnvironment($documentImportContext->getEnvironment());
            }
            $this->entityManager->persist($currentRevision);
        }

        $this->dataService->setMetaFields($newRevision);

        if ($documentImportContext->shouldIndexInDefaultEnv() && $documentImportContext->shouldFinalize()) {
            $indexConfig = [
                '_index' => $documentImportContext->getEnvironment()->getAlias(),
                '_type' => $documentImportContext->getContentType()->getName(),
                '_id' => $ouuid,
            ];

            if ($newRevision->getContentType()->getHavePipelines()) {
                $indexConfig['pipeline'] = $this->instanceId.$documentImportContext->getContentType()->getName();
            }
            $body = $newRevision->getRawData();

            $this->bulker->index($documentImportContext->getContentType()->getName(), $ouuid, $documentImportContext->getEnvironment()->getAlias(), $body);
        }

        $newRevision->setDraft(!$documentImportContext->shouldFinalize());
        $this->entityManager->persist($newRevision);
        $this->revisionRepository->finaliseRevision($documentImportContext->getContentType(), $ouuid, $newRevision->getStartTime(), $documentImportContext->getLockUser());
        $this->revisionRepository->publishRevision($newRevision, $newRevision->getDraft());
    }
}
