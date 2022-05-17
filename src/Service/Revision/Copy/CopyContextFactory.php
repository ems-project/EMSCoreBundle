<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision\Copy;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;

final class CopyContextFactory
{
    /** @var EnvironmentService */
    private $environmentService;
    /** @var ElasticaService */
    private $elasticaService;

    public function __construct(EnvironmentService $environmentService, ElasticaService $elasticaService)
    {
        $this->environmentService = $environmentService;
        $this->elasticaService = $elasticaService;
    }

    public function fromJSON(string $environmentName, string $searchJSON, string $mergeJSON = ''): CopyContext
    {
        $search = $this->elasticaService->convertElasticsearchBody([$this->getEnvironment($environmentName)->getAlias()], [], $this->jsonDecode($searchJSON));
        $copyRequest = new CopyContext($search);

        if ('' !== $mergeJSON) {
            $copyRequest->setMerge($this->jsonDecode($mergeJSON));
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

    private function jsonDecode(string $json): array
    {
        $decoded = \json_decode($json, true);

        if (null === $decoded || JSON_ERROR_NONE !== \json_last_error()) {
            throw new \InvalidArgumentException(\sprintf('Invalid JSON %s (%s)', $json, \json_last_error_msg()));
        }

        return $decoded;
    }
}
