<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use EMS\CoreBundle\Entity\WysiwygStylesSet;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use EMS\Helpers\Html\MimeTypes;
use EMS\Helpers\Standard\Json;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Server\Builder;

/**
 * @phpstan-type WysiwygCssClass array{class: string, element: string, styleName: string, source: string}
 * @phpstan-type WysiwygCssClassSet array{name: string, classes: list<WysiwygCssClass>}
 */
final readonly class ElasticmsMcpResourceWysiwygStyleService
{
    private const string RESOURCE_URI = 'elasticms://wysiwyg-style-sets/css-classes';
    private const string RESOURCE_TEMPLATE_URI = 'elasticms://wysiwyg-style-sets/{name}/css-classes';

    public function __construct(private WysiwygStylesSetService $wysiwygStylesSetService)
    {
    }

    public function addWysiwygStyleResources(Builder $builder): void
    {
        $builder
            ->addResource(
                handler: $this->getCssClasses(...),
                uri: self::RESOURCE_URI,
                name: 'wysiwyg_style_sets_css_classes',
                title: 'WYSIWYG CSS classes',
                description: 'Lists CSS classes configured in all elasticMS WYSIWYG style sets, including the HTML element each class applies to.',
                mimeType: MimeTypes::APPLICATION_JSON->value,
            )
            ->addResourceTemplate(
                handler: $this->getCssClassesForStyleSetName(...),
                uriTemplate: self::RESOURCE_TEMPLATE_URI,
                name: 'wysiwyg_style_set_css_classes',
                title: 'WYSIWYG style set CSS classes',
                description: 'Lists CSS classes configured in one elasticMS WYSIWYG style set. Replace {name} with the URL-encoded style set name.',
                mimeType: MimeTypes::APPLICATION_JSON->value,
            );
    }

    /**
     * @return array{styleSets: list<WysiwygCssClassSet>}
     */
    public function getCssClasses(): array
    {
        return [
            'styleSets' => \array_values(\array_map(
                $this->buildStyleSetCssClasses(...),
                $this->wysiwygStylesSetService->getStylesSets(),
            )),
        ];
    }

    /**
     * @return WysiwygCssClassSet
     */
    public function getCssClassesForStyleSetName(string $name): array
    {
        $decodedName = \rawurldecode($name);
        $styleSet = $this->wysiwygStylesSetService->getByName($decodedName);
        if (!$styleSet instanceof WysiwygStylesSet) {
            throw new ResourceNotFoundException(\str_replace('{name}', $name, self::RESOURCE_TEMPLATE_URI));
        }

        return $this->buildStyleSetCssClasses($styleSet);
    }

    /**
     * @return WysiwygCssClassSet
     */
    private function buildStyleSetCssClasses(WysiwygStylesSet $styleSet): array
    {
        $classes = [];
        foreach (Json::decode($styleSet->getConfig()) as $style) {
            if (!\is_array($style)) {
                continue;
            }

            $classes = [
                ...$classes,
                ...$this->extractCssClasses($style),
            ];
        }

        foreach ($this->splitCssClasses($styleSet->getTableDefaultCss()) as $class) {
            $classes[] = [
                'class' => $class,
                'element' => 'table',
                'styleName' => 'Table default',
                'source' => 'tableDefaultCss',
            ];
        }

        return [
            'name' => $styleSet->getName(),
            'classes' => $classes,
        ];
    }

    /**
     * @param array<mixed> $style
     *
     * @return list<WysiwygCssClass>
     */
    private function extractCssClasses(array $style): array
    {
        $attributes = $style['attributes'] ?? null;
        if (!\is_array($attributes) || !\is_string($attributes['class'] ?? null)) {
            return [];
        }

        if (!\is_string($style['element'] ?? null) || !\is_string($style['name'] ?? null)) {
            return [];
        }

        return \array_map(
            static fn (string $class): array => [
                'class' => $class,
                'element' => $style['element'],
                'styleName' => $style['name'],
                'source' => 'config',
            ],
            $this->splitCssClasses($attributes['class']),
        );
    }

    /**
     * @return list<string>
     */
    private function splitCssClasses(string $classes): array
    {
        $splitClasses = \preg_split('/\s+/', \trim($classes), -1, \PREG_SPLIT_NO_EMPTY);
        if (false === $splitClasses) {
            return [];
        }

        return \array_values(\array_unique($splitClasses));
    }
}
