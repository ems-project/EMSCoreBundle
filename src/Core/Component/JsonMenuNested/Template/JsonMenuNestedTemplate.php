<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Template;

use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfig;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfigException;
use EMS\Helpers\Standard\Json;
use Twig\Environment;
use Twig\TemplateWrapper;

class JsonMenuNestedTemplate
{
    private TemplateWrapper $template;
    private ?TemplateWrapper $configTemplate;
    /** @var array<string, mixed> */
    private array $contextBlock = [];

    public const TWIG_TEMPLATE = '@EMSCore/components/json_menu_nested/template.twig';

    public function __construct(
        private readonly JsonMenuNestedConfig $config,
        private readonly Environment $twig
    ) {
        $this->template = $this->twig->load(self::TWIG_TEMPLATE);
        $this->configTemplate = $this->config->template ? $this->twig->load($this->config->template) : null;
        $this->setContextBlock($config->contextBlock);
    }

    /**
     * @param array<mixed> $context
     */
    public function block(string $blockName, array $context = []): string
    {
        $context = $this->getContext($context);
        $isPrivate = \str_starts_with($blockName, '_');

        if (!$isPrivate && $this->configTemplate && $this->configTemplate->hasBlock($blockName)) {
            return $this->configTemplate->renderBlock($blockName, $context);
        }

        return $this->template->renderBlock($blockName, $context);
    }

    public function hasBlock(string $blockName): bool
    {
        return $this->configTemplate?->hasBlock($blockName) || $this->template->hasBlock($blockName);
    }

    /**
     * @param array<mixed> $blockContext
     *
     * @return array<mixed>
     */
    private function getContext(array $blockContext): array
    {
        $context = [...$blockContext, ...$this->contextBlock, ...$this->config->context];
        $context['template'] = $this;
        $context['config'] = $this->config;

        return $context;
    }

    private function setContextBlock(?string $contextBlock): void
    {
        if (null === $contextBlock || null === $this->configTemplate) {
            return;
        }

        if (!$this->configTemplate->hasBlock($contextBlock)) {
            throw new JsonMenuNestedConfigException(\sprintf('Context block "%s" not defined', $contextBlock));
        }

        $blockResult = $this->block($contextBlock);
        if (!Json::isJson($blockResult)) {
            throw new JsonMenuNestedConfigException(\sprintf('Context block "%s" not returning json', $contextBlock));
        }

        $this->contextBlock = Json::decode($blockResult);
    }
}
