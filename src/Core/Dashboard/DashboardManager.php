<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard;

use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\Dashboard;
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

    public function update(Dashboard $dashboard): void
    {
        if (0 === $dashboard->getOrderKey()) {
            $dashboard->setOrderKey($this->dashboardRepository->counter() + 1);
        }
        $encoder = new Encoder();
        $name = $dashboard->getName();
        if (null === $name) {
            throw new \RuntimeException('Unexpected null name');
        }
        $webalized = $encoder->webalize($name);
        if (null === $webalized) {
            throw new \RuntimeException('Unexpected null webalized name');
        }
        $dashboard->setName($webalized);
        $this->dashboardRepository->create($dashboard);
    }

    /**
     * @param string[] $ids
     */
    public function reorderByIds(array $ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $channel = $this->dashboardRepository->getById($id);
            $channel->setOrderKey($counter++);
            $this->dashboardRepository->create($channel);
        }
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->dashboardRepository->getByIds($ids) as $dashboard) {
            $this->delete($dashboard);
        }
    }

    public function delete(Dashboard $dashboard): void
    {
        $name = $dashboard->getName();
        $this->dashboardRepository->delete($dashboard);
        $this->logger->warning('log.service.dashboard.delete', [
            'name' => $name,
        ]);
    }
}
