<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class AjaxService
{
    public function __construct(private Environment $templating, private TranslatorInterface $translator)
    {
    }

    public function getTemplating(): Environment
    {
        return $this->templating;
    }

    public function newAjaxModel(string $templateName): AjaxModal
    {
        $template = $this->templating->load($templateName);

        return new AjaxModal($template, $this->translator);
    }
}
