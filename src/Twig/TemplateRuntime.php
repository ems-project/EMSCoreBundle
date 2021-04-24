<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

class TemplateRuntime
{
    public function generateAjaxEditButton(string $emsLink, string $label, bool $labelHtmlSafe = false): string
    {
        list($contentType, $ouuid) = \explode(':', $emsLink);

        return \sprintf('<a href="#" data-content-type="%s" data-ouuid="%s">%s</a>', $contentType, $ouuid, $label);
    }
}
