<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Data\TableAbstract;
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
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (isset($context['option']) && TableAbstract::REMOVE_ACTION === $context['option']) {
            return $this->revisionRepository->getByIds($from, $size, $context['selected']);
        }

        if (isset($context['option']) && TableAbstract::ADD_ACTION === $context['option'] && \count($context['selected']) > 0) {
            return $this->revisionRepository->getWithoutIds($from, $size, $context);
        }

        return $this->revisionRepository->get($from, $size, $context);
    }

    public function getEntityName(): string
    {
        return 'revision';
    }

    /**
     * @param mixed $context
     */
    public function count(string $searchValue = '', $context = null): int
    {
        if (isset($context['option']) && TableAbstract::REMOVE_ACTION === $context['option']) {
            return $this->revisionRepository->counterByIds($context['selected']);
        }

        if (isset($context['option']) && TableAbstract::ADD_ACTION === $context['option'] && \count($context['selected']) > 0) {
            return $this->revisionRepository->counterWithoutIds($context);
        }

        return $this->revisionRepository->counter($context);
    }
}
