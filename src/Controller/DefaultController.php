<?php

namespace EMS\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends AbstractController
{
    public function documentation(): Response
    {
        return $this->render('@EMSCore/default/documentation.html.twig');
    }
}
