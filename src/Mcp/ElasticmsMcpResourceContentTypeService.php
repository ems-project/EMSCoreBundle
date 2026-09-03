<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\Helpers\Html\HtmlHelper;
use EMS\Helpers\Html\MimeTypes;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Server\Builder;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @phpstan-type ContentTypeResource array{name: string, singularName: string, pluralName: string, description: string, defaultEnvironment: string}
 */
final readonly class ElasticmsMcpResourceContentTypeService
{
    private const string RESOURCE_URI = 'elasticms://content-types/descriptions';
    private const string RESOURCE_TEMPLATE_URI = 'elasticms://content-types/{name}/description';

    public function __construct(
        private ContentTypeService $contentTypeService,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function addContentTypeResources(Builder $builder): void
    {
        $builder
            ->addResource(
                handler: $this->getContentTypes(...),
                uri: self::RESOURCE_URI,
                name: 'content_types_descriptions',
                title: 'Content type descriptions',
                description: 'Lists readable elasticMS content types with their plain-text descriptions.',
                mimeType: MimeTypes::APPLICATION_JSON->value,
            )
            ->addResourceTemplate(
                handler: $this->getContentTypeByName(...),
                uriTemplate: self::RESOURCE_TEMPLATE_URI,
                name: 'content_type_description',
                title: 'Content type description',
                description: 'Returns one readable elasticMS content type with its plain-text description. Replace {name} with the URL-encoded content type name.',
                mimeType: MimeTypes::APPLICATION_JSON->value,
            );
    }

    /**
     * @return array{contentTypes: list<ContentTypeResource>}
     */
    public function getContentTypes(): array
    {
        $contentTypes = [];
        foreach ($this->contentTypeService->getAll() as $contentType) {
            if (!$this->isViewableContentType($contentType)) {
                continue;
            }

            $contentTypes[] = $this->buildContentType($contentType);
        }

        return [
            'contentTypes' => $contentTypes,
        ];
    }

    /**
     * @return ContentTypeResource
     */
    public function getContentTypeByName(string $name): array
    {
        $decodedName = \rawurldecode($name);
        $contentType = $this->contentTypeService->getByName($decodedName);
        if (!$contentType instanceof ContentType || !$this->isViewableContentType($contentType)) {
            throw new ResourceNotFoundException(\str_replace('{name}', $name, self::RESOURCE_TEMPLATE_URI));
        }

        return $this->buildContentType($contentType);
    }

    /**
     * @return ContentTypeResource
     */
    private function buildContentType(ContentType $contentType): array
    {
        return [
            'name' => $contentType->getName(),
            'singularName' => (string) $contentType->getSingularName(),
            'pluralName' => (string) $contentType->getPluralName(),
            'description' => HtmlHelper::toText((string) $contentType->getDescription()),
            'defaultEnvironment' => $contentType->giveEnvironment()->getName(),
        ];
    }

    private function isViewableContentType(ContentType $contentType): bool
    {
        return $contentType->giveEnvironment()->getManaged()
            && $contentType->isActive()
            && $this->authorizationChecker->isGranted($contentType->role(ContentTypeRoles::VIEW));
    }
}
