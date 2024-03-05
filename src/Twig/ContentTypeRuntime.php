<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\ContentTypeService;
use Twig\Extension\RuntimeExtensionInterface;

class ContentTypeRuntime implements RuntimeExtensionInterface
{
    public function __construct(private readonly ContentTypeService $contentTypeService)
    {
    }

    public function getContentType(string $name): ?ContentType
    {
        $contentType = $this->contentTypeService->getByName($name);

        return $contentType ?: null;
    }

    /**
     * @return ContentType[]
     */
    public function getContentTypes(): array
    {
        return $this->contentTypeService->getAll();
    }

    /**
     * @return array<string, ?string>
     */
    public function getContentTypeVersionTags(string $contentTypeName): array
    {
        $contentType = $this->contentTypeService->giveByName($contentTypeName);

        return $this->contentTypeService->getVersionTagsByContentType($contentType);
    }
}
