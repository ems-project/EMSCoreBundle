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

    public function drafts(Request $request): Response
    {
        $drafts = [];
        $includeRawData = $request->query->getBoolean('includeRawData', false);
        $circles = $request->query->all('circles');

        foreach ($this->revisionService->findAllDrafts($circles) as $revision) {
            $draft = [
                'id' => (string) $revision->getId(),
                'ouuid' => $revision->getOuuid(),
                'circles' => $revision->getCircles(),
                'save_date' => $revision->getDraftSaveDate()?->format(\DATE_ATOM),
                'created' => $revision->getCreated()->format(\DATE_ATOM),
            ];

            if ($includeRawData) {
                $draft['raw_data'] = $revision->getRawData();
            }

            $drafts[] = $draft;
        }

        return new JsonResponse($drafts);
    }
}
