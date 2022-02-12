<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Admin;

use EMS\CoreBundle\Core\Entity\EntitiesHelper;
use EMS\CoreBundle\Entity\EntityInterface;
use EMS\CoreBundle\Exception\EntityServiceNotFoundException;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EntitiesController
{
    private EntitiesHelper $entitiesHelper;

    public function __construct(EntitiesHelper $entitiesHelper)
    {
        $this->entitiesHelper = $entitiesHelper;
    }

    public function index(string $entity): Response
    {
        $entityService = $this->getEntityService($entity);
        $count = $entityService->count();
        $names = [];
        for ($from = 0; $from < $count; $from += 10) {
            foreach ($entityService->get($from, 10, null, 'asc', '') as $entity) {
                if ($entity instanceof EntityInterface) {
                    $names[] = $entity->getName();
                } else {
                    $names[] = \strval($entity->getId());
                }
            }
        }

        return new JsonResponse($names);
    }

    public function get(string $entity, string $name): Response
    {
        $entityService = $this->getEntityService($entity);
        $item = $entityService->getByItemName($name);
        if (null === $item) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($item);
    }

    public function update(string $entity, string $name, Request $request): Response
    {
        $entityService = $this->getEntityService($entity);
        $entity = $entityService->getByItemName($name);
        $content = $request->getContent();
        if (!\is_string($content)) {
            throw new \RuntimeException('Unexpected non string content');
        }

        if (null === $entity) {
            $entity = $entityService->createEntityFromJson($content, $name);
        } else {
            $entity = $entityService->updateEntityFromJson($entity, $content);
        }

        return new JsonResponse([
            'id' => $entity->getId(),
        ]);
    }

    public function delete(string $entity, string $name): Response
    {
        $entityService = $this->getEntityService($entity);
        $id = $entityService->deleteByItemName($name);

        return new JsonResponse([
            'id' => $id,
        ]);
    }

    public function create(string $entity, Request $request): Response
    {
        $entityService = $this->getEntityService($entity);
        $content = $request->getContent();
        if (!\is_string($content)) {
            throw new \RuntimeException('Unexpected non string content');
        }
        $entity = $entityService->createEntityFromJson($content);

        return new JsonResponse([
            'id' => \strval($entity->getId()),
        ]);
    }

    protected function getEntityService(string $entity): EntityServiceInterface
    {
        try {
            $entityService = $this->entitiesHelper->getEntityService($entity);
        } catch (EntityServiceNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage());
        }

        return $entityService;
    }
}
