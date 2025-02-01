<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Admin;

use EMS\CommonBundle\Common\EMSLinkCollection;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MetaController
{
    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly RevisionService $revisionService,
    ) {
    }

    public function infoDocuments(Request $request): JsonResponse
    {
        $content = Json::decode(Type::string($request->getContent()));

        $environments = $content['environments'] ?? [];
        $emsLinks = EMSLinkCollection::fromEmsIds($content['emsLinks'] ?? []);

        return new JsonResponse(['info' => $this->revisionService->getInfos($environments, $emsLinks)]);
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

    public function contentTypes(): Response
    {
        $contentTypes = [];
        foreach ($this->contentTypeService->getAll() as $contentType) {
            if ($contentType->getActive() && !$contentType->getDeleted() && $contentType->giveEnvironment()->getManaged()) {
                $contentTypes[] = $contentType->getName();
            }
        }

        return new JsonResponse($contentTypes);
    }
}
