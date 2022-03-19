<?php

namespace EMS\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    public function documentation(): Response
    {
        return $this->render('@EMSCore/default/documentation.html.twig');
    }
}
