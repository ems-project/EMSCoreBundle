<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Config;

use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ContentTypeController
{
    private ContentTypeService $contentTypeService;

    public function __construct(ContentTypeService $contentTypeService)
    {
        $this->contentTypeService = $contentTypeService;
    }

    public function index(): Response
    {
        $names = [];
        foreach ($this->contentTypeService->getAll() as $contentType) {
            if ($contentType->getDeleted()) {
                continue;
            }
            $names[] = $contentType->getName();
        }

        return new JsonResponse($names);
    }
}
