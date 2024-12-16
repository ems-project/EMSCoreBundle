<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Template;

use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfig;
use Twig\Environment;

class JsonMenuNestedTemplateFactory
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $templateNamespace,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function create(JsonMenuNestedConfig $config, array $context = []): JsonMenuNestedTemplate
    {
        return new JsonMenuNestedTemplate($this->twig, $config, $this->templateNamespace, $context);
    }
}
