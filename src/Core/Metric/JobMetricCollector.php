<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Metric;

use EMS\CommonBundle\Common\Metric\MetricCollectorInterface;
use EMS\CoreBundle\Repository\JobRepository;
use EMS\Helpers\Standard\DateTime;
use Prometheus\CollectorRegistry;

class JobMetricCollector implements MetricCollectorInterface
{
    public const string NAMESPACE = 'emsco_job';

    /** @var array<int, string[]> */
    private array $gauges = [
        ['last_created', 'Timestamp of the last created job'],
        ['last_modified', 'Timestamp of the last modified job'],
        ['count_jobs', 'Count jobs'],
        ['count_jobs_pending', 'Count jobs pending'],
        ['count_jobs_started', 'Count jobs started'],
        ['count_jobs_done', 'Count jobs done'],
        ['count_jobs_failed', 'Count jobs failed'],
    ];

    public function __construct(
        private readonly JobRepository $jobRepository,
    ) {
    }

    public function getName(): string
    {
        return self::NAMESPACE;
    }

    public function collect(CollectorRegistry $collectorRegistry): void
    {
        $jobSummary = $this->jobRepository->summary();
        $namespace = $this->getName();

        foreach ($this->gauges as [$name, $help]) {
            $gauge = $collectorRegistry->getOrRegisterGauge($namespace, $name, $help, ['tag']);

            foreach ($jobSummary as $info) {
                if ('last_created' === $name || 'last_modified' === $name) {
                    $value = (float) \strtotime((string) $info[$name]);
                } else {
                    $value = (float) $info[$name];
                }

                $gauge->set($value, [$info['tag'] ?? '_admin']);
            }
        }
    }

    public function validUntil(): int
    {
        return DateTime::create('+30 seconds')->getTimestamp();
    }
}
