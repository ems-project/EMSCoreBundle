<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use Twig\Environment;
use Twig\TemplateWrapper;

class MediaLibraryTemplate
{
    private TemplateWrapper $template;
    private ?TemplateWrapper $configTemplate;

    public const BLOCK_FILE_ROW_HEADER = 'mediaLibraryFileRowHeader';
    public const BLOCK_FILE_ROW = 'mediaLibraryFileRow';

    public function __construct(
        private readonly Environment $twig,
        private readonly MediaLibraryConfig $config
    ) {
        $this->template = $this->twig->load('@EMSCore/components/media_library/template.html.twig');
        $this->configTemplate = $this->config->template ? $this->twig->load($this->config->template) : null;
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
