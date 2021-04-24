<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use Twig\Environment;
use Twig\TemplateWrapper;

class TemplateRuntime
{
    private Environment $twig;
    /** @var TemplateWrapper[] */
    private array $templates;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function generateAjaxEditButton(string $emsLink, string $label, bool $labelHtmlSafe = false): string
    {
        list($contentTypename, $ouuid) = \explode(':', $emsLink);
        $template = $this->getTemplate('@EMSCore/runtime/ajax-edit-button.html.twig');

        return $this->twig->render($template, [
            'contentTypeName' => $contentTypename,
            'ouuid' => $ouuid,
            'label' => $label,
            'labelHtmlSafe' => $labelHtmlSafe,
        ]);
    }

    private function getTemplate(string $templateName): TemplateWrapper
    {
        if (isset($this->templates[$templateName])) {
            return $this->templates[$templateName];
        }
        $this->templates[$templateName] = $this->twig->load($templateName);

        return $this->templates[$templateName];
    }
}
