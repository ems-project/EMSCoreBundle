<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Copy;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;

final class CopyContextFactory
{
    private EnvironmentService $environmentService;
    private ElasticaService $elasticaService;

    public function __construct(EnvironmentService $environmentService, ElasticaService $elasticaService)
    {
        $this->environmentService = $environmentService;
        $this->elasticaService = $elasticaService;
    }

    public function fromJSON(string $environmentName, string $searchJSON, string $mergeJSON = ''): CopyContext
    {
        $search = $this->elasticaService->convertElasticsearchBody([$this->getEnvironment($environmentName)->getAlias()], [], Json::decode($searchJSON));
        $copyRequest = new CopyContext($search);

        if ('' !== $mergeJSON) {
            $copyRequest->setMerge(Json::decode($mergeJSON));
        }

        return $copyRequest;
    }

    private function getEnvironment(string $name): Environment
    {
        $environment = $this->environmentService->getByName($name);

        if (false === $environment) {
            throw new \InvalidArgumentException(\sprintf('Environment %s not found', $name));
        }

        return $environment;
    }
}
