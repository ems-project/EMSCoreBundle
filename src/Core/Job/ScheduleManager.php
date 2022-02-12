<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Job;

use Cron\CronExpression;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\Schedule;
use EMS\CoreBundle\Repository\ScheduleRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;

class ScheduleManager implements EntityServiceInterface
{
    private ScheduleRepository $scheduleRepository;
    private LoggerInterface $logger;

    public function __construct(ScheduleRepository $scheduleRepository, LoggerInterface $logger)
    {
        $this->scheduleRepository = $scheduleRepository;
        $this->logger = $logger;
    }

    /**
     * @return Schedule[]
     */
    public function getAll(): array
    {
        return $this->scheduleRepository->getAll();
    }

    public function update(Schedule $schedule): void
    {
        $this->setNextRun($schedule);
        if (0 === $schedule->getOrderKey()) {
            $schedule->setOrderKey($this->scheduleRepository->counter() + 1);
        }
        $this->scheduleRepository->create($schedule);
    }

    public function delete(Schedule $schedule): void
    {
        $name = $schedule->getName();
        $this->scheduleRepository->delete($schedule);
        $this->logger->warning('log.service.schedule.delete', [
            'name' => $name,
        ]);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->scheduleRepository->getByIds($ids) as $schedule) {
            $this->delete($schedule);
        }
    }

    /**
     * @param string[] $ids
     */
    public function reorderByIds(array $ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $schedule = $this->scheduleRepository->getById($id);
            $schedule->setOrderKey($counter++);
            $this->scheduleRepository->create($schedule);
        }
    }

    public function isSortable(): bool
    {
        return true;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->scheduleRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'schedule';
    }

    public function count(string $searchValue = '', $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected non-null object');
        }

        return $this->scheduleRepository->counter($searchValue);
    }

    public function setNextRun(Schedule $schedule): void
    {
        $cron = new CronExpression($schedule->getCron());
        $schedule->setNextRun($cron->getNextRunDate());
    }

    public function findNext(): ?Schedule
    {
        $schedule = $this->scheduleRepository->findNext();
        if (null === $schedule) {
            return null;
        }

        $schedule->setPreviousRun(new \DateTime());
        $this->setNextRun($schedule);
        $this->update($schedule);

        return $schedule;
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->scheduleRepository->getById($name);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }

    public function deleteByItemName(string $name = null): string
    {
        throw new \RuntimeException('deleteByItemName method not yet implemented');
    }
}
