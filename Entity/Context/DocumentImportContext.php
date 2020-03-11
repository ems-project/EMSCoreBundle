<?php


namespace EMS\CoreBundle\Entity\Context;


use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;

class DocumentImportContext
{

    /** @var ContentType */
    private $contentType;
    /** @var string */
    private $contentTypeName;
    /** @var string */
    private $lockUser;
    /** @var bool */
    private $rawImport;
    /** @var EntityManager */
    private $entityManager;
    /** @var RevisionRepository */
    private $revisionRepository;
    /** @var bool */
    private $indexInDefaultEnv;
    /** @var bool */
    private $signData;
    /** @var ContentTypeRepository */
    private $contentTypeRepository;
    /** @var Environment */
    private $environment;
    /** @var bool */
    private $finalize;
    /** @var bool */
    private $force;

    public function __construct(EntityManager $entityManager, string $contentTypeName, string $lockUser, bool $rawImport, bool $signData, bool $indexInDefaultEnv, bool $finalize, bool $force)
    {
        $this->contentTypeName = $contentTypeName;
        $this->indexInDefaultEnv = $indexInDefaultEnv;
        $this->signData = $signData;
        $this->lockUser = $lockUser;
        $this->rawImport = $rawImport;
        $this->finalize = $finalize;
        $this->entityManager = $entityManager;
        $this->force = $force;

        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);

        $repository = $this->entityManager->getRepository('EMSCoreBundle:Revision');
        if (! $repository instanceof RevisionRepository) {
            throw new \Exception('Can not get the RevisionRepository');
        }
        $this->revisionRepository = $repository;

        $repository = $this->entityManager->getRepository('EMSCoreBundle:ContentType');
        if (! $repository instanceof ContentTypeRepository) {
            throw new \Exception('Can not get the ContentTypeRepository');
        }
        $this->contentTypeRepository = $repository;

        $contentType = $this->contentTypeRepository->findOneBy(array("name" => $this->contentTypeName, 'deleted' => false));
        if (! $contentType instanceof ContentType) {
            throw new \Exception(sprintf('Content type %s not found', $this->contentTypeName));
        }
        $this->contentType = $contentType;
        $this->environment = $this->contentType->getEnvironment();
    }

    public function getContentType(): ContentType
    {
        return $this->contentType;
    }

    public function getContentTypeName(): string
    {
        return $this->contentTypeName;
    }

    public function getLockUser(): string
    {
        return $this->lockUser;
    }

    public function isRawImport(): bool
    {
        return $this->rawImport;
    }

    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    public function getRevisionRepository(): RevisionRepository
    {
        return $this->revisionRepository;
    }

    public function isIndexInDefaultEnv(): bool
    {
        return $this->indexInDefaultEnv;
    }

    public function isSignData(): bool
    {
        return $this->signData;
    }

    public function getContentTypeRepository(): ContentTypeRepository
    {
        return $this->contentTypeRepository;
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function isFinalize(): bool
    {
        return $this->finalize;
    }

    public function isForce(): bool
    {
        return $this->force;
    }
}
