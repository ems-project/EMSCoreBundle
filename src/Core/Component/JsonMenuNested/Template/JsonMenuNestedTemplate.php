<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Template;

use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfig;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Template\Context\JsonMenuNestedTemplateContext;
use Twig\Environment;
use Twig\TemplateWrapper;

class JsonMenuNestedTemplate
{
    private readonly TemplateWrapper $template;
    private readonly ?TemplateWrapper $configTemplate;
    public JsonMenuNestedTemplateContext $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly JsonMenuNestedConfig $config,
        private readonly Environment $twig,
        private readonly string $templateNamespace,
        array $context = []
    ) {
        $this->template = $this->twig->load("@$this->templateNamespace/components/json_menu_nested/template.twig");
        $this->configTemplate = $this->config->template ? $this->twig->load($this->config->template) : null;

        $this->context = new JsonMenuNestedTemplateContext([
            ...['template' => $this, 'config' => $this->config, 'menu' => $this->config->jsonMenuNested],
            ...$this->config->context,
            ...$context,
        ]);

        if (null !== $this->configTemplate && null !== $this->config->contextBlock) {
            $this->block($this->config->contextBlock);
        }
    }

    /**
     * @param array<mixed> $blockContext
     */
    public function block(string $blockName, array $blockContext = []): string
    {
        $context = [...$this->context->raw, ...$blockContext];

        if ($this->configTemplate && $this->configTemplate->hasBlock($blockName)) {
            return $this->configTemplate->renderBlock($blockName, $context);
        }

        return $this->template->renderBlock($blockName, $context);
    }

    public function hasBlock(string $blockName): bool
    {
        return $this->configTemplate?->hasBlock($blockName) || $this->template->hasBlock($blockName);
    }
}
