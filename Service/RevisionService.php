<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\RevisionRepository;

final class RevisionService
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

    public function getByOuuidAndContentTypeAndEnvironment(string $ouuid, string $contentType, string $env): ?Revision
    {
        $contentType = $this->contentTypeService->getByName($contentType);
        $environment = $this->environmentService->getAliasByName($env);

        return $this->revisionRepository->findByOuuidAndContentTypeAndEnvironment(
            $contentType,
            $ouuid,
            $environment
        );
    }
}
