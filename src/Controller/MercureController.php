<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\Mercure\MercureService;
use Symfony\Component\HttpFoundation\JsonResponse;

class MercureController
{
    public function __construct(private readonly MercureService $mercureService)
    {
    }

    public function getToken(): JsonResponse
    {
        return new JsonResponse($this->mercureService->generateToken(expiresAt: '+30 minutes'));
    }
}
