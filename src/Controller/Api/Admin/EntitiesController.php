<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Admin;

use EMS\CoreBundle\Core\Entity\EntitiesHelper;
use EMS\CoreBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Exception\EntityServiceNotFoundException;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EntitiesController
{
    public function __construct(private readonly EntitiesHelper $entitiesHelper, private readonly LoggerInterface $logger)
    {
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
                    $names[] = (string) $entity->getId();
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

    public function getEntityNames(): Response
    {
        return new JsonResponse($this->entitiesHelper->getEntityNames());
    }

    public function update(string $entity, string $name, Request $request): Response
    {
        $entityService = $this->getEntityService($entity);
        $entityObject = $entityService->getByItemName($name);
        $content = $request->getContent();
        if (!\is_string($content)) {
            throw new \RuntimeException('Unexpected non string content');
        }

        if (null === $entityObject) {
            $entityObject = $entityService->createEntityFromJson($content, $name);
            $this->logger->notice('api.admin.entities.create', [
                'entity' => $entity,
                'name' => $name,
                'id' => $entityObject->getId(),
            ]);
        } else {
            $entityObject = $entityService->updateEntityFromJson($entityObject, $content);
            $this->logger->notice('api.admin.entities.update', [
                'entity' => $entity,
                'name' => $name,
                'id' => $entityObject->getId(),
            ]);
        }

        return new JsonResponse([
            'id' => (string) $entityObject->getId(),
        ]);
    }

    public function delete(string $entity, string $name): Response
    {
        $entityService = $this->getEntityService($entity);
        $id = $entityService->deleteByItemName($name);
        $this->logger->notice('api.admin.entities.delete', [
            'entity' => $entity,
            'name' => $name,
            'id' => $id,
        ]);

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
        $entityObject = $entityService->createEntityFromJson($content);
        $this->logger->notice('api.admin.entities.create', [
            'entity' => $entity,
            'id' => $entityObject->getId(),
        ]);

        return new JsonResponse([
            'id' => (string) $entityObject->getId(),
        ]);
    }

    public function jobStatus(Job $job): Response
    {
        return new JsonResponse([
            'id' => (string) $job->getId(),
            'created' => $job->getCreated()->format('c'),
            'modified' => $job->getModified()->format('c'),
            'command' => $job->getCommand(),
            'user' => $job->getUser(),
            'done' => $job->getDone(),
            'output' => $job->getOutput(),
            'started' => $job->getStarted(),
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
