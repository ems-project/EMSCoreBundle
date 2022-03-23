<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Metric;

use EMS\CommonBundle\Contracts\Metric\EMSMetricsCollectorInterface;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\EnvironmentService;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricNotFoundException;

class MetricCollector implements EMSMetricsCollectorInterface
{
    private RevisionRepository $revisionRepository;
    private ContentTypeRepository $contentTypeRepository;
    private ElasticaService $elasticaService;
    private EnvironmentService $environmentService;

    public function __construct(RevisionRepository $revisionRepository, ContentTypeRepository $contentTypeRepository, ElasticaService $elasticaService, EnvironmentService $environmentService)
    {
        $this->revisionRepository = $revisionRepository;
        $this->contentTypeRepository = $contentTypeRepository;
        $this->elasticaService = $elasticaService;
        $this->environmentService = $environmentService;
    }

    /**
     * @throws MetricNotFoundException
     */
    public function collect(CollectorRegistry $registry): void
    {
        $status = $this->elasticaService->getHealthStatus('green', '15s');
        $boolStatus = 0;
        if ('green' === $status or 'yellow' === $status) {
            $boolStatus = 3;
        } elseif ('yellow' === $status) {
            $boolStatus = 2;
        }

        $healthStatus = $registry->getOrRegisterGauge(
            'emsco',
            'elasticms_status',
            'Health status of the cluster',
            ['status']
        );
        $healthStatus->set($boolStatus, [$status]);

        $envs = $this->environmentService->getEnvironments();
        $count = $registry->getOrRegisterGauge(
            'emsco',
            'count_total_',
            'The number of Content Type',
            ['env']
        );
        foreach ($envs as $env) {
            $totalContentTypeByEnv = $this->contentTypeRepository->countContentTypeByEnvironment($env->getId());
            $count->set(\floatval($totalContentTypeByEnv), [$env->getName()]);
        }

        $documentCounter = $registry->getOrRegisterGauge(
            'emsco',
            'revisions_total',
            'Number of document by Content Type',
            ['contenttype']
        );
        $allContentTypes = $this->contentTypeRepository->findAll();
        foreach ($allContentTypes as $cT) {
            $revision = $this->revisionRepository->findByContentType($cT);
            $documentCounter->set(\count($revision), [$cT->getName()]);
        }
    }
}
