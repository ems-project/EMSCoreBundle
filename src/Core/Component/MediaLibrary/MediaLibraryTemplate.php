<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use Twig\Environment;
use Twig\TemplateWrapper;

class MediaLibraryTemplate
{
    private TemplateWrapper $template;
    private TemplateWrapper $templateElements;
    private ?TemplateWrapper $configTemplate;

    public const BLOCK_HEADER = 'mediaLibraryHeader';
    public const BLOCK_FILE_ROW_HEADER = 'mediaLibraryFileRowHeader';
    public const BLOCK_FILE_ROW = 'mediaLibraryFileRow';

    public function __construct(
        private readonly Environment $twig,
        private readonly MediaLibraryConfig $config,
        private readonly string $templateNamespace
    ) {
        $this->template = $this->twig->load("@$this->templateNamespace/components/media_library/template.html.twig");
        $this->templateElements = $this->twig->load("@$this->templateNamespace/components/media_library/elements.html.twig");

        $this->configTemplate = $this->config->template ? $this->twig->load($this->config->template) : null;
    }

    /**
     * @param array<mixed> $context
     */
    public function renderHeader(array $context = []): string
    {
        $elements = ['buttonHome', 'buttonAddFolder', 'buttonUpload', 'breadcrumb'];
        $elementsContext = \array_merge([
            'id' => $this->config->getId(),
            'folder' => null,
        ], $context, );

        foreach ($elements as $element) {
            $context[$element] = $this->templateElements->renderBlock($element, $elementsContext);
        }

        return $this->block(self::BLOCK_HEADER, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function block(string $blockName, array $context = []): string
    {
        $context = \array_merge($context, $this->config->context);
        $context['config'] = $this->config;

        if ($this->configTemplate && $this->configTemplate->hasBlock($blockName)) {
            return $this->configTemplate->renderBlock($blockName, $context);
        }

        return $this->template->renderBlock($blockName, $context);
    }
}
