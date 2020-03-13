<?php


namespace EMS\CoreBundle\Service;


use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Context\DocumentImportContext;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Form\Form\RevisionType;
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

    public function __construct(Registry $doctrine, DataService $dataService, FormFactoryInterface $formFactory, Bulker $bulker, string $instanceId)
    {
        $this->dataService = $dataService;
        $this->formFactory = $formFactory;
        $this->doctrine = $doctrine;
        $this->bulker = $bulker;
        $this->instanceId = $instanceId;
    }

    public function initDocumentImporterContext(ContentType $contentType, string $lockUser, bool $rawImport, bool $signData, bool $indexInDefaultEnv, int $bulkSize, bool $finalize, bool $force) : DocumentImportContext
    {
        $entityManager = $this->doctrine->getManager();
        $this->bulker->setEnableSha1(false);
        $this->bulker->setSize($bulkSize);
        return new DocumentImportContext($entityManager, $contentType, $lockUser, $rawImport, $signData, $indexInDefaultEnv, $finalize, $force);
    }


    public function flushAndSend(DocumentImportContext $documentImportContext)
    {
        $documentImportContext->getEntityManager()->flush();
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

        $currentRevision = $documentImportContext->getRevisionRepository()->getCurrentRevision($documentImportContext->getContentType(), $ouuid);

        if ($currentRevision && $currentRevision->getDraft()) {
            if (!$documentImportContext->shouldForce()) {
                //TODO: activate the newRevision when it's available
                throw new CantBeFinalizedException('a draft is already in progress for the document', 0, null/*, $newRevision*/);
            }

            $this->dataService->discardDraft($currentRevision, true, $documentImportContext->getLockUser());
            $currentRevision = $documentImportContext->getRevisionRepository()->getCurrentRevision($documentImportContext->getContentType(), $ouuid);
        }
        if (!$documentImportContext->shouldRawImport()) {
            $this->submitData($documentImportContext, $newRevision, $currentRevision ?? $this->dataService->getEmptyRevision($documentImportContext->getContentType(), $documentImportContext->getLockUser()));
        }

        if ($currentRevision) {
            $currentRevision->setEndTime($newRevision->getStartTime());
            $currentRevision->setDraft(false);
            $currentRevision->setAutoSave(null);
            if ($documentImportContext->shouldFinalize()) {
                $currentRevision->removeEnvironment($documentImportContext->getEnvironment());
            }
            $currentRevision->setLockBy($documentImportContext->getLockUser());
            $currentRevision->setLockUntil($newRevision->getLockUntil());
            $documentImportContext->getEntityManager()->persist($currentRevision);
        }

        $this->dataService->setMetaFields($newRevision);

        if ($documentImportContext->shouldIndexInDefaultEnv() && $documentImportContext->shouldFinalize()) {
            $indexConfig = [
                '_index' => $documentImportContext->getEnvironment()->getAlias(),
                '_type' => $documentImportContext->getContentType()->getName(),
                '_id' => $ouuid,
            ];

            if ($newRevision->getContentType()->getHavePipelines()) {
                $indexConfig['pipeline'] = $this->instanceId . $documentImportContext->getContentType()->getName();
            }
            $body = $documentImportContext->shouldSignData() ? $this->dataService->sign($newRevision) : $newRevision->getRawData();

            $this->bulker->index($indexConfig, $body);
        }

        $newRevision->setDraft(!$documentImportContext->shouldFinalize());
        $documentImportContext->getEntityManager()->persist($newRevision);
        $documentImportContext->getRevisionRepository()->finaliseRevision($documentImportContext->getContentType(), $ouuid, $newRevision->getStartTime(), $documentImportContext->getLockUser());
        $documentImportContext->getRevisionRepository()->publishRevision($newRevision, $newRevision->getDraft());
        $documentImportContext->getEntityManager()->flush();
    }
}
