<?php

namespace EMS\CoreBundle\Core\Dashboard;

use EMS\CoreBundle\Repository\DashboardRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;

class DashboardManager implements EntityServiceInterface
{
    private DashboardRepository $dashboardRepository;
    private LoggerInterface $logger;

    public function __construct(DashboardRepository $dashboardRepository, LoggerInterface $logger)
    {
        $this->dashboardRepository = $dashboardRepository;
        $this->logger = $logger;
    }

    public function isSortable(): bool
    {
        return true;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->dashboardRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'dashboard';
    }

    public function count(string $searchValue = '', $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->dashboardRepository->counter($searchValue);
    }
}
