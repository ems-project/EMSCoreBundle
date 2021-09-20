<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\ORM\NoResultException;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\ReleaseRevision;
use EMS\CoreBundle\Repository\ReleaseRepository;
use Psr\Log\LoggerInterface;

final class ReleaseService implements EntityServiceInterface
{
    /** @var ReleaseRepository */
    private $releaseRepository;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var DataService */
    private $dataService;
    /** @var ReleaseRevisionService */
    private $releaseRevisionService;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(ReleaseRepository $releaseRepository, ContentTypeService $contentTypeService, DataService $dataService, ReleaseRevisionService $releaseRevisionService, LoggerInterface $logger)
    {
        $this->releaseRepository = $releaseRepository;
        $this->contentTypeService = $contentTypeService;
        $this->dataService = $dataService;
        $this->releaseRevisionService = $releaseRevisionService;
        $this->logger = $logger;
    }

    /**
     * @return Release[]
     */
    public function getAll(): array
    {
        return $this->releaseRepository->getAll();
    }

    public function add(Release $release): Release
    {
        $this->update($release);

        return $release;
    }

    public function update(Release $release): void
    {
        $encoder = new Encoder();
        $name = $release->getName();
        if (null == $name) {
            throw new \RuntimeException('Unexpected null name');
        }
        $webalized = $encoder->webalize($name);
        if (null == $webalized) {
            throw new \RuntimeException('Unexpected null webalized name');
        }
        $release->setName($webalized);
        $this->releaseRepository->create($release);
    }

    /**
     * @param array<string> $emsLinks
     * 
     * @throws NoResultException
     */
    public function addRevisions(Release $release, array $emsLinks): void
    {
        foreach ($emsLinks as $emsLink) {
            $eL = \explode(':', $emsLink);
            $releaseRevision = new ReleaseRevision();
            $releaseRevision->setRelease($release);
            $releaseRevision->setRevisionOuuid($eL[1]);

            $contentType = $this->contentTypeService->giveByName($eL[0]);
            $releaseRevision->setContentType($contentType);

            try {
                $revision = $this->dataService->getRevisionByEnvironment($eL[1], $contentType, $release->getEnvironmentSource());
            } catch (\NoResultException $e) {
                $revision = null;
            }

            $releaseRevision->setRevision($revision);
            $release->addRevision($releaseRevision);
        }
        $this->releaseRepository->create($release);
    }

    /**
     * @param array<string> $emsLinks
     */
    public function removeRevisions(Release $release, array $emsLinks): void
    {
        foreach ($emsLinks as $emsLink) {
            $eL = \explode(':', $emsLink);
            $contentType = $this->contentTypeService->giveByName($eL[0]);
            $ouuid = $eL[1];
            $releaseRevision = $this->releaseRevisionService->findToRemove($release, $ouuid, $contentType);
            $this->releaseRevisionService->remove($releaseRevision);
        }
    }

    public function delete(Release $release): void
    {
        $name = $release->getName();
        $this->releaseRepository->delete($release);
        $this->logger->warning('log.service.release.delete', [
            'name' => $name,
        ]);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->releaseRepository->getByIds($ids) as $release) {
            $this->delete($release);
        }
    }

    public function isSortable(): bool
    {
        return false;
    }

    /**
     * @param mixed $context
     *
     * @return Release[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->releaseRepository->get($from, $size);
    }

    public function getEntityName(): string
    {
        return 'release';
    }

    /**
     * @param mixed $context
     */
    public function count(string $searchValue = '', $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected non-null object');
        }

        return $this->releaseRepository->counter();
    }
}
