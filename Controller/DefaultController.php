<?php

namespace EMS\CoreBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    
    /**
     * @Route("/documentation", name="documentation")
     */
    public function documentationAction(Request $request)
    {
    	// replace this example code with whatever you need
    	return $this->render('EMSCoreBundle:default:documentation.html.twig');
    }
    

    /**
     * @Route("/coming-soon", name="coming-soon")
     */
    public function comingSoonAction()
    {
    	// replace this example code with whatever you need
    	return $this->render('EMSCoreBundle:default:coming-soon.html.twig');
    }
}
