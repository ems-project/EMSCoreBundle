<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Template;

use EMS\CoreBundle\Core\Component\AbstractComponentTemplate;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfig;
use Twig\Environment;

class JsonMenuNestedTemplate extends AbstractComponentTemplate
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        Environment $twig,
        JsonMenuNestedConfig $config,
        string $templateNamespace,
        array $context = [],
    ) {
        parent::__construct(
            $twig,
            "@$templateNamespace/components/json_menu_nested/template.twig",
            $config->template
        );

        $this->context->append([
            ...['config' => $config, 'menu' => $config->jsonMenuNested],
            ...$config->context,
            ...$context,
        ]);

        if (null !== $this->configTemplate && null !== $config->contextBlock) {
            $this->block($config->contextBlock);
        }
    }
}
