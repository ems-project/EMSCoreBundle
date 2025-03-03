<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\UI\Page\Navigation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class DefaultController extends AbstractController
{
    public function __construct(private readonly string $templateNamespace)
    {
    }

    public function documentation(): Response
    {
        return $this->render("@$this->templateNamespace/default/documentation.html.twig", [
            'title' => t('key.documentation', [], 'emsco-core'),
            'breadcrumb' => new Navigation()->add(t('key.documentation', [], 'emsco-core')),
        ]);
    }
}
