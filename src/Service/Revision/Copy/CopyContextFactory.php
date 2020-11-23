<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision\Copy;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;

final class CopyContextFactory
{
    /** @var EnvironmentService */
    private $environmentService;

    public function __construct(EnvironmentService $environmentService)
    {
        $this->environmentService = $environmentService;
    }

    public function fromJSON(string $environmentName, string $searchJSON, string $mergeJSON = ''): CopyContext
    {
        $environment = $this->getEnvironment($environmentName);
        $copyRequest = new CopyContext($environment, $this->jsonDecode($searchJSON));

        if ('' !== $mergeJSON) {
            $copyRequest->setMerge($this->jsonDecode($mergeJSON));
        }

        return $copyRequest;
    }

    private function getEnvironment(string $name): Environment
    {
        $environment = $this->environmentService->getByName($name);

        if (false === $environment) {
            throw new \InvalidArgumentException(sprintf('Environment %s not found', $name));
        }

        return $environment;
    }

    private function jsonDecode(string $json): array
    {
        $decoded = \json_decode($json, true);

        if (null === $decoded || JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(sprintf('Invalid JSON %s (%s)', $json, json_last_error_msg()));
        }

        return $decoded;
    }
}
