<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\ContentTypeService;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

class ContentTypeExtension
{
    public function __construct(private readonly ContentTypeService $contentTypeService)
    {
    }

    #[AsTwigFilter(name: 'emsco_get_content_type')]
    public function getContentType(string $name): ?ContentType
    {
        $contentType = $this->contentTypeService->getByName($name);

        return $contentType ?: null;
    }

    /**
     * @return array<string, ?string>
     */
    #[AsTwigFunction(name: 'emsco_get_content_type_version_tags')]
    public function getContentTypeVersionTags(string $contentTypeName): array
    {
        $contentType = $this->contentTypeService->giveByName($contentTypeName);

        return $this->contentTypeService->getVersionTagsByContentType($contentType);
    }

    /**
     * @return ContentType[]
     */
    #[AsTwigFunction(name: 'emsco_get_content_types')]
    public function getContentTypes(): array
    {
        return $this->contentTypeService->getAll();
    }
}
