<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Core\Environment\EnvironmentsRevision;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\EnvironmentService;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

readonly class EnvironmentExtension
{
    public function __construct(private EnvironmentService $environmentService)
    {
    }

    /**
     * @return string[]
     */
    #[AsTwigFunction(name: 'emsco_get_default_environment_names')]
    public function getDefaultEnvironmentNames(): array
    {
        $environments = $this->environmentService->getEnvironments();
        $defaultEnvironments = \array_filter($environments, fn (Environment $e) => $e->getInDefaultSearch());

        return \array_map(fn (Environment $e) => $e->getName(), $defaultEnvironments);
    }

    #[AsTwigFilter(name: 'emsco_get_environment')]
    public function getEnvironment(string $name): ?Environment
    {
        $environment = $this->environmentService->getAliasByName($name);

        return $environment ?: null;
    }

    #[AsTwigFunction(name: 'emsco_get_environments_revision')]
    public function getEnvironmentsRevision(Revision $revision): EnvironmentsRevision
    {
        return $this->environmentService->getEnvironmentsByRevision($revision);
    }

    /**
     * @return Environment[]
     */
    #[AsTwigFunction(name: 'emsco_get_environments')]
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
