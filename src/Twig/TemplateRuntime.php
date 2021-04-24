<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use Twig\Environment;

class TemplateRuntime
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function generateAjaxEditButton(string $emsLink, string $label, bool $labelHtmlSafe = false): string
    {
        list($contentType, $ouuid) = \explode(':', $emsLink);

        return \sprintf('<a href="#" data-content-type="%s" data-ouuid="%s">%s</a>', $contentType, $ouuid, $label);
    }
}
