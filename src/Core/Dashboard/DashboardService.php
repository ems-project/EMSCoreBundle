<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard;

use EMS\CoreBundle\Core\Dashboard\Services\DashboardInterface;

class DashboardService
{
    /** @var array<string, DashboardInterface> */
    private readonly array $dashboards;

    /**
     * @param \Traversable<DashboardInterface> $viewTypes
     */
    public function __construct(\Traversable $viewTypes)
    {
        $this->dashboards = \iterator_to_array($viewTypes);
    }

    /**
     * @return string[]
     */
    public function getIds(): array
    {
        return \array_keys($this->dashboards);
    }

    public function get(string $id): DashboardInterface
    {
        return $this->dashboards[$id];
    }
}
