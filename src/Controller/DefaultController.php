<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends AbstractController
{
    public function __construct(private readonly string $templateNamespace)
    {
    }

    public function documentation(): Response
    {
        return $this->render("@$this->templateNamespace/default/documentation.html.twig");
    }
}
