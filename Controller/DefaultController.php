<?php

namespace Ems\CoreBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        
    	return $this->redirectToRoute('notifications.inbox');
    	
//         return $this->render('default/index.html.twig', [
//             'base_dir' => realpath($this->container->getParameter('kernel.root_dir').'/..'),
//         ]);
    }
    
    /**
     * @Route("/documentation", name="documentation")
     */
    public function documentationAction(Request $request)
    {
    	// replace this example code with whatever you need
    	return $this->render('default/documentation.html.twig');
    }
    

    /**
     * @Route("/coming-soon", name="coming-soon")
     */
    public function comingSoonAction()
    {
    	// replace this example code with whatever you need
    	return $this->render('default/coming-soon.html.twig');
    }
}
