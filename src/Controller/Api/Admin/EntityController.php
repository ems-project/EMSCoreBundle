<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Admin;

use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EntityController
{
    private ContentTypeService $contentTypeService;

    public function __construct(ContentTypeService $contentTypeService)
    {
        $this->contentTypeService = $contentTypeService;
    }

    public function index(string $entity): Response
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

    public function get(string $entity, string $name): Response
    {
        $contentType = $this->contentTypeService->getByName($name);
        if (false === $contentType) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($contentType);
    }

    public function update(string $entity, string $name, Request $request): Response
    {
        $contentType = $this->contentTypeService->getByName($name);
        if (false === $contentType) {
            throw new NotFoundHttpException();
        }
        $content = $request->getContent();
        if (!\is_string($content)) {
            throw new \RuntimeException('Unexpected non string content');
        }
        $this->contentTypeService->updateFromJson($contentType, $content, true, true);

        return new JsonResponse();
    }
}
