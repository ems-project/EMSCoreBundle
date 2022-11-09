<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use Twig\Extension\RuntimeExtensionInterface;

class EnvironmentRuntime implements RuntimeExtensionInterface
{
    private EnvironmentService $environmentService;

    public function __construct(EnvironmentService $environmentService)
    {
        $this->environmentService = $environmentService;
    }

    /**
     * @return string[]
     */
    public function getDefaultEnvironmentNames(): array
    {
        $environments = $this->environmentService->getEnvironments();
        $defaultEnvironments = \array_filter($environments, fn (Environment $e) => $e->getInDefaultSearch());

        return \array_map(fn (Environment $e) => $e->getName(), $defaultEnvironments);
    }

    public function getEnvironment(string $name): ?Environment
    {
        $environment = $this->environmentService->getAliasByName($name);

        return $environment ?: null;
    }

    /**
     * @return Environment[]
     */
    public function getEnvironments(bool $sort = false): array
    {
        $environments = $this->environmentService->getEnvironments();

        if ($sort) {
            \uasort(
                $environments,
                fn (Environment $a, Environment $b) => $a->getOrderKey() <=> $b->getOrderKey()
            );
        }

        return $environments;
    }
}
