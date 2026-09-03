<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\Helpers\Html\HtmlHelper;
use EMS\Helpers\Html\MimeTypes;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Server\Builder;

/**
 * @phpstan-type EnvironmentResource array{name: string, label: string, description: string}
 */
final readonly class ElasticmsMcpResourceEnvironmentService
{
    private const string RESOURCE_URI = 'elasticms://environments/descriptions';
    private const string RESOURCE_TEMPLATE_URI = 'elasticms://environments/{name}/description';

    public function __construct(private EnvironmentService $environmentService)
    {
    }

    public function addEnvironmentResources(Builder $builder): void
    {
        $builder
            ->addResource(
                handler: $this->getEnvironments(...),
                uri: self::RESOURCE_URI,
                name: 'environments_descriptions',
                title: 'Environment descriptions',
                description: 'Lists managed elasticMS environments with their plain-text descriptions.',
                mimeType: MimeTypes::APPLICATION_JSON->value,
            )
            ->addResourceTemplate(
                handler: $this->getEnvironmentByName(...),
                uriTemplate: self::RESOURCE_TEMPLATE_URI,
                name: 'environment_description',
                title: 'Environment description',
                description: 'Returns one managed elasticMS environment with its plain-text description. Replace {name} with the URL-encoded environment name.',
                mimeType: MimeTypes::APPLICATION_JSON->value,
            );
    }

    /**
     * @return array{environments: list<EnvironmentResource>}
     */
    public function getEnvironments(): array
    {
        return [
            'environments' => \array_values(\array_map(
                $this->buildEnvironment(...),
                $this->environmentService->getManagedEnvironement(),
            )),
        ];
    }

    /**
     * @return EnvironmentResource
     */
    public function getEnvironmentByName(string $name): array
    {
        $decodedName = \rawurldecode($name);
        $environment = $this->environmentService->getByName($decodedName);
        if (!$environment instanceof Environment || !$environment->getManaged()) {
            throw new ResourceNotFoundException(\str_replace('{name}', $name, self::RESOURCE_TEMPLATE_URI));
        }

        return $this->buildEnvironment($environment);
    }

    /**
     * @return EnvironmentResource
     */
    private function buildEnvironment(Environment $environment): array
    {
        return [
            'name' => $environment->getName(),
            'label' => $environment->getLabel(),
            'description' => HtmlHelper::toText($environment->getDescription()),
        ];
    }
}
