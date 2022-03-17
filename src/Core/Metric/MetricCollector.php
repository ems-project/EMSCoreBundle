<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Metric;

use EMS\CommonBundle\Contracts\Metric\EMSMetricsCollectorInterface;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Prometheus\CollectorRegistry;

class MetricCollector implements EMSMetricsCollectorInterface
{
    private RevisionRepository $revisionRepository;
    private ContentTypeRepository $contentTypeRepository;

    /**
     * @param RevisionRepository $revisionRepository
     * @param ContentTypeRepository $contentTypeRepository
     */
    public function __construct(RevisionRepository $revisionRepository, ContentTypeRepository $contentTypeRepository)
    {
        $this->revisionRepository = $revisionRepository;
        $this->contentTypeRepository = $contentTypeRepository;
    }

    /**
     * @param CollectorRegistry $registry
     */
    public function collect(CollectorRegistry $registry): void
    {
        $registry['revisions'] = $this->revisionRepository->count([]);
        $registry['contentType'] = $this->contentTypeRepository->count([]);
    }
}