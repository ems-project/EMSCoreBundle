<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Repository\RevisionRepository;

class RevisionService
{
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var EnvironmentService */
    private $environmentService;
    /** @var RevisionRepository */
    private $revisionRepository;

    public function __construct(ContentTypeService $contentTypeService, EnvironmentService $environmentService, RevisionRepository $revisionRepository)
    {
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->revisionRepository = $revisionRepository;
    }

    public function getIdByOuuidAndContentTypeAndEnvironment(string $ouuid, string $contentType, string $env) : ?array
    {
        $contentType = $this->contentTypeService->getByName($contentType)->getId();
        $env = $this->environmentService->getAliasByName($env)->getId();
        return $this->revisionRepository->findIdByOuuidAndContentTypeAndEnvironment($ouuid, (int) $contentType, (int) $env) ?? null;
    }
}
