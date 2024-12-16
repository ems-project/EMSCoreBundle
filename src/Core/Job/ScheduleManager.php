<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Job;

use Cron\CronExpression;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\Schedule;
use EMS\CoreBundle\Repository\ScheduleRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;

class ScheduleManager implements EntityServiceInterface
{
    public function __construct(
        private readonly ScheduleRepository $scheduleRepository,
        private readonly LoggerInterface $logger,
    ) {
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
        $encoder = new Encoder();
        $webalized = $encoder->webalize($schedule->getName());
        $schedule->setName($webalized);
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

    /**
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [
            'schedules',
            'Schedule',
            'Schedules',
        ];
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

    public function findNext(?string $tag = null): ?Schedule
    {
        $schedule = $this->scheduleRepository->findNext($tag);
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
        return $this->scheduleRepository->getByName($name);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        $schedule = Schedule::fromJson($json, $entity);
        $this->update($schedule);

        return $schedule;
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $schedule = Schedule::fromJson($json);
        if (null !== $name && $schedule->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Filter name mismatched: %s vs %s', $schedule->getName(), $name));
        }
        $this->update($schedule);

        return $schedule;
    }

    public function deleteByItemName(string $name): string
    {
        $schedule = $this->scheduleRepository->getByName($name);
        if (null === $schedule) {
            throw new \RuntimeException(\sprintf('Filter %s not found', $name));
        }
        $id = $schedule->getId();
        $this->delete($schedule);

        return $id;
    }
}
