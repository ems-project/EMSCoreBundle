<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
    /**
     * @return Response
     * @Route("/documentation", name="documentation")
     */
    public function documentationAction()
    {
        // replace this example code with whatever you need
        return $this->render('@EMSCore/default/documentation.html.twig');
    }

    /**
     * @return Response
     * @Route("/coming-soon", name="coming-soon")
     */
    public function comingSoonAction()
    {
        // replace this example code with whatever you need
        return $this->render('@EMSCore/default/coming-soon.html.twig');
    }
}
