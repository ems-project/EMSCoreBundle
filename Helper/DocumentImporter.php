<?php


namespace EMS\CoreBundle\Helper;


use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\DataService;
use Symfony\Component\Form\FormFactoryInterface;

class DocumentImporter
{
    /** @var ContentType */
    private $contentType;
    /** @var string */
    private $contentTypeName;
    /** @var DataService */
    private $dataService;
    /** @var string */
    private $lockUser;
    /** @var bool */
    private $rawImport;
    /** @var EntityManager */
    private $entityManager;
    /** @var RevisionRepository */
    private $revisionRepository;
    /** @var FormFactoryInterface */
    private $formFactory;
    /** @var bool */
    private $indexInDefaultEnv;
    /** @var bool */
    private $signData;
    /** @var Bulker */
    private $bulker;
    /** @var ContentTypeRepository */
    private $contentTypeRepository;
    /** @var Environment */
    private $environment;
    /** @var int */
    private $bulkSize;
    /** @var string  */
    private $instanceId;
    /** @var bool */
    private $finalize;
    /** @var bool */
    private $force;

    public function __construct(DataService $dataService, EntityManager $entityManager, FormFactoryInterface $formFactory, Bulker $bulker, string $instanceId, string $contentTypeName, string $lockUser, bool $rawImport, bool $signData, bool $indexInDefaultEnv, int $bulkSize, bool $finalize, bool $force)
    {
        $this->contentTypeName = $contentTypeName;
        $this->indexInDefaultEnv = $indexInDefaultEnv;
        $this->instanceId = $instanceId;
        $this->signData = $signData;
        $this->lockUser = $lockUser;
        $this->rawImport = $rawImport;
        $this->bulkSize = $bulkSize;
        $this->finalize = $finalize;
        $this->dataService = $dataService;
        $this->bulker = $bulker;
        $this->entityManager = $entityManager;
        $this->formFactory = $formFactory;
        $this->force = $force;

        //https://stackoverflow.com/questions/9699185/memory-leaks-symfony2-doctrine2-exceed-memory-limit
        //https://stackoverflow.com/questions/13093689/is-there-a-way-to-free-the-memory-allotted-to-a-symfony-2-form-object
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);

        $this->bulker->setEnableSha1(false);


        $repository = $entityManager->getRepository('EMSCoreBundle:Revision');
        if (! $repository instanceof RevisionRepository) {
            throw new \Exception('Can not get the RevisionRepository');
        }
        $this->revisionRepository = $repository;

        $repository = $entityManager->getRepository('EMSCoreBundle:ContentType');
        if (! $repository instanceof ContentTypeRepository) {
            throw new \Exception('Can not get the ContentTypeRepository');
        }
        $this->contentTypeRepository = $repository;

        $this->bulker->setSize($this->bulkSize);

        $contentType = $this->contentTypeRepository->findOneBy(array("name" => $this->contentTypeName, 'deleted' => false));
        if (! $contentType instanceof ContentType) {
            throw new \Exception(sprintf('Content type %s not found', $this->contentTypeName));
        }
        $this->contentType = $contentType;
        $this->environment = $this->contentType->getEnvironment();
    }

    public function flushAndSend()
    {
        $this->entityManager->flush();

        if ($this->finalize) {
            $this->bulker->send(true);
        }
    }


    private function submitData(Revision $revision, Revision $previousRevision = null)
    {
        $revisionType = $this->formFactory->create(RevisionType::class, $revision, ['migration' => true, 'with_warning' => false, 'raw_data' => $revision->getRawData()]);
        $viewData = $this->dataService->getSubmitData($revisionType->get('data'));

        $revisionType->setData($previousRevision);

        $revisionType->submit(['data' => $viewData]);
        $data = $revisionType->get('data')->getData();
        $revision->setData($data);
        $objectArray = $revision->getRawData();

        $this->dataService->propagateDataToComputedField($revisionType->get('data'), $objectArray, $this->contentType, $this->contentType->getName(), $revision->getOuuid(), true);
        $revision->setRawData($objectArray);

        unset($revisionType);
    }

    public function importDocument(string $ouuid, array $rawData)
    {
        $newRevision = $this->dataService->getEmptyRevision($this->contentType, $this->lockUser);
        if (!$this->finalize) {
            $newRevision->removeEnvironment($this->environment);
        }
        $newRevision->setOuuid($ouuid);
        $newRevision->setRawData($rawData);

        $currentRevision = $this->revisionRepository->getCurrentRevision($this->contentType, $ouuid);

        if ($currentRevision && $currentRevision->getDraft()) {
            if (!$this->force) {
                //TODO: activate the newRevision when it's available
                throw new CantBeFinalizedException('a draft is already in progress for the document', 0, null/*, $newRevision*/);
            }

            $this->dataService->discardDraft($currentRevision, true, $this->lockUser);
            $currentRevision = $this->revisionRepository->getCurrentRevision($this->contentType, $ouuid);
        }
        if (!$this->rawImport) {
            $this->submitData($newRevision, $currentRevision ?? $this->dataService->getEmptyRevision($this->contentType, $this->lockUser));
        }

        if ($currentRevision) {
            $currentRevision->setEndTime($newRevision->getStartTime());
            $currentRevision->setDraft(false);
            $currentRevision->setAutoSave(null);
            if ($this->finalize) {
                $currentRevision->removeEnvironment($this->environment);
            }
            $currentRevision->setLockBy($this->lockUser);
            $currentRevision->setLockUntil($newRevision->getLockUntil());
            $this->entityManager->persist($currentRevision);
        }

        $this->dataService->setMetaFields($newRevision);

        if ($this->indexInDefaultEnv && $this->finalize) {
            $indexConfig = [
                '_index' => $this->environment->getAlias(),
                '_type' => $this->contentType->getName(),
                '_id' => $ouuid,
            ];

            if ($newRevision->getContentType()->getHavePipelines()) {
                $indexConfig['pipeline'] = $this->instanceId . $this->contentType->getName();
            }
            $body = $this->signData ? $this->dataService->sign($newRevision) : $newRevision->getRawData();

            $this->bulker->index($indexConfig, $body);
        }

        $newRevision->setDraft(!$this->finalize);
        $this->entityManager->persist($newRevision);
        $this->revisionRepository->finaliseRevision($this->contentType, $ouuid, $newRevision->getStartTime(), $this->lockUser);
        $this->revisionRepository->publishRevision($newRevision, $newRevision->getDraft());
        $this->entityManager->flush();
    }
}
