<?php

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use Twig\Extension\RuntimeExtensionInterface;


class RevisionRuntime implements RuntimeExtensionInterface
{
    /**@var ContentTypeService */
    private $contentTypeService;
    /** @var DataService */
    private $dataService;
    /** @var EnvironmentService */
    private $environmentService;

    public function __construct(ContentTypeService $contentTypeService, DataService $dataService, EnvironmentService $environmentService)
    {
        $this->contentTypeService = $contentTypeService;
        $this->dataService = $dataService;
        $this->environmentService = $environmentService;
    }

    public function getRevisionId($ouuid, $env, $contentType)
    {
        $contentType = $this->contentTypeService->getByName($contentType)->getId();
        $env = $this->environmentService->getAliasByName($env)->getId();
        return $this->dataService->getIdByOuuidAndContentTypeAndEnvironment($ouuid, $contentType, $env)['id'] ?? null;
    }
}