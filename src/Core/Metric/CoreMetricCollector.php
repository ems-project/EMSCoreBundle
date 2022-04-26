<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Metric;

use EMS\CommonBundle\Common\Metric\MetricCollectorInterface;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use Prometheus\CollectorRegistry;

final class CoreMetricCollector implements MetricCollectorInterface
{
    private EnvironmentService $environmentService;

    private const NAMESPACE = 'ems_core';

    public function __construct(EnvironmentService $environmentService)
    {
        $this->environmentService = $environmentService;
    }

    public function collect(CollectorRegistry $collectorRegistry): void
    {
        $stats = $this->environmentService->getEnvironmentsStats();

   //     $collectorRegistry->wipeStorage();




        $counter = $collectorRegistry->getOrRegisterGauge(
            self::NAMESPACE,
            'count_revisions',
            'Counter revisions by environment',
            ['ems_core']
        );

        $test = $collectorRegistry->getMetricFamilySamples();

        foreach ($stats as $stat) {
            list($environment, $amount, $deleted) = \array_values($stat);

            if (!$environment instanceof Environment) {
                continue;
            }

            $counter->set($amount, [$environment->getLabel()]);
        }
    }
}