<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Data;

use Doctrine\ORM\NonUniqueResultException;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublishController
{
    public function __construct(
        private readonly PublishService $publishService,
        private readonly RevisionService $revisionService,
        private readonly ContentTypeService $contentTypeService,
        private readonly EnvironmentService $environmentService,
    ) {
    }

    public function publish(string $contentTypeName, string $ouuid, string $targetEnvironmentName, ?Revision $revision = null): JsonResponse
    {
        if (null === $revision) {
            $contentType = $this->contentTypeService->giveByName($contentTypeName);
            $revision = $this->revisionService->getCurrentRevisionForEnvironment($ouuid, $contentType, $contentType->giveEnvironment());
        } elseif ($revision->giveContentType()->getName() !== $contentTypeName) {
            throw new \RuntimeException(\sprintf('Content type mismatch for revision %d: Expected %s is in fact of type %s', $revision->getId(), $contentTypeName, $revision->giveContentType()->getName()));
        }
        if (null === $revision) {
            throw new \RuntimeException(\sprintf('Revision not found for OUUID %s and Content type %s', $ouuid, $contentTypeName));
        }

        $targetEnvironment = $this->environmentService->giveByName($targetEnvironmentName);

        try {
            $publishedCounter = $this->publishService->publish($revision, $targetEnvironment);
        } catch (NonUniqueResultException) {
            throw new NotFoundHttpException('Document not found');
        }

        return new JsonResponse([
            'success' => true,
            'already-published' => 0 === $publishedCounter,
        ]);
    }
}
