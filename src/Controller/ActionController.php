<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\Action\ActionRevisionService;
use Symfony\Component\HttpFoundation\JsonResponse;

class ActionController
{
    public function __construct(
        private readonly ActionRevisionService $actionRevisionService
    ) {
    }

    public function revisionField(int $revisionId, int $fieldId): JsonResponse
    {
        return new JsonResponse($this->actionRevisionService->handle($revisionId, $fieldId));
    }
}
