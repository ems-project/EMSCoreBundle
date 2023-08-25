<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Template;

use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfig;
use Twig\Environment;

class JsonMenuNestedTemplateFactory
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function create(JsonMenuNestedConfig $config): JsonMenuNestedTemplate
    {
        return new JsonMenuNestedTemplate($config, $this->twig);
    }
}
