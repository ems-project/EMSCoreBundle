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
    private $shouldRawImport;
    /** @var EntityManager */
    private $entityManager;
    /** @var RevisionRepository */
    private $revisionRepository;
    /** @var bool */
    private $shouldIndexInDefaultEnv;
    /** @var bool */
    private $shouldSignData;
    /** @var ContentTypeRepository */
    private $contentTypeRepository;
    /** @var Environment */
    private $environment;
    /** @var bool */
    private $shouldFinalize;
    /** @var bool */
    private $shouldForce;

    public function __construct(EntityManager $entityManager, string $contentTypeName, string $lockUser, bool $shouldRawImport, bool $signData, bool $shouldIndexInDefaultEnv, bool $shouldFinalize, bool $shouldForceImport)
    {
        $this->contentTypeName = $contentTypeName;
        $this->shouldIndexInDefaultEnv = $shouldIndexInDefaultEnv;
        $this->shouldSignData = $signData;
        $this->lockUser = $lockUser;
        $this->shouldRawImport = $shouldRawImport;
        $this->shouldFinalize = $shouldFinalize;
        $this->entityManager = $entityManager;
        $this->shouldForce = $shouldForceImport;

        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);

        $repository = $this->entityManager->getRepository('EMSCoreBundle:Revision');
        if (! $repository instanceof RevisionRepository) {
            throw new \Exception('Can not get the RevisionReposisitory');
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

    public function shouldRawImport(): bool
    {
        return $this->shouldRawImport;
    }

    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    public function getRevisionRepository(): RevisionRepository
    {
        return $this->revisionRepository;
    }

    public function shouldIndexInDefaultEnv(): bool
    {
        return $this->shouldIndexInDefaultEnv;
    }

    public function shouldSignData(): bool
    {
        return $this->shouldSignData;
    }

    public function getContentTypeRepository(): ContentTypeRepository
    {
        return $this->contentTypeRepository;
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function shouldFinalize(): bool
    {
        return $this->shouldFinalize;
    }

    public function shouldForce(): bool
    {
        return $this->shouldForce;
    }
}
