<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class AjaxService
{
    public function __construct(private readonly Environment $templating, private readonly TranslatorInterface $translator)
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

    public function ajaxModalTemplate(string $templateName): AjaxModalTemplate
    {
        $template = $this->templating->load($templateName);

        return new AjaxModalTemplate($template);
    }
}
