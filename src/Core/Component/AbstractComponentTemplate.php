<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component;

use Twig\Environment;
use Twig\TemplateWrapper;

abstract class AbstractComponentTemplate
{
    protected TemplateWrapper $template;
    protected ?TemplateWrapper $configTemplate = null;

    public ComponentTemplateContext $context;

    public function __construct(
        Environment $twig,
        string $templateName,
        ?string $configTemplateName,
    ) {
        $this->template = $twig->load($templateName);
        if ($configTemplateName) {
            $this->configTemplate = $twig->load($configTemplateName);
        }

        $this->context = new ComponentTemplateContext(['template' => $this]);
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
