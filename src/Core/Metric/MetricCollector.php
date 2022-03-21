<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Metric;

use EMS\CommonBundle\Contracts\Metric\EMSMetricsCollectorInterface;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricNotFoundException;

class MetricCollector implements EMSMetricsCollectorInterface
{
    private RevisionRepository $revisionRepository;
    private ContentTypeRepository $contentTypeRepository;

    public function __construct(RevisionRepository $revisionRepository, ContentTypeRepository $contentTypeRepository)
    {
        $this->revisionRepository = $revisionRepository;
        $this->contentTypeRepository = $contentTypeRepository;
    }

    /**
     * @throws MetricNotFoundException
     */
    public function collect(CollectorRegistry $registry): void
    {
        $registry = $registry->getOrRegisterGauge(
            'EMSCore',
            'Content_Type_Counter',
            'The number of content type',
            ['numberOfCt']
        );
        $registry->set(($this->contentTypeRepository->count([])), ['ctCounter']);
    }
}
