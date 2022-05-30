<?php

namespace EMS\CoreBundle\Controller\Api\Admin;

use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MetaController
{
    private ContentTypeService $contentTypeService;

    public function __construct(ContentTypeService $contentTypeService)
    {
        $this->contentTypeService = $contentTypeService;
    }

    public function contentType(string $contentTypeName): Response
    {
        $contentType = $this->contentTypeService->getByName($contentTypeName);
        if (false === $contentType) {
            throw new NotFoundHttpException(\sprintf('Content type %s not found', $contentTypeName));
        }

        return new JsonResponse([
            'alias' => $contentType->giveEnvironment()->getAlias(),
            'environment' => $contentType->giveEnvironment()->getName(),
        ]);
    }
}
