<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\RevisionRepository;
use Psr\Log\LoggerInterface;

final class ReleaseRevisionService implements EntityServiceInterface
{
    /** @var RevisionRepository */
    private $revisionRepository;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(RevisionRepository $revisionRepository, LoggerInterface $logger)
    {
        $this->revisionRepository = $revisionRepository;
        $this->logger = $logger;
    }

    public function isSortable(): bool
    {
        return false;
    }

    /**
     * @param mixed $context
     *
     * @return array<Revision>
     */
    public function get(int $from, int $size, $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->revisionRepository->get($from, $size);
    }

    public function getEntityName(): string
    {
        return 'revision';
    }

    /**
     * @param mixed $context
     */
    public function count($context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected non-null object');
        }

        return $this->revisionRepository->counter();
    }
}
