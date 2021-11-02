<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CoreBundle\Entity\Dashboard;
use Symfony\Component\HttpFoundation\Response;

interface DashboardInterface
{
    public function getResponse(Dashboard $dashboard): Response;
}
