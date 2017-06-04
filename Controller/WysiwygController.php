<?php

namespace EMS\CoreBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Form\Form\WysiwygProfileType;
use Symfony\Component\Form\FormError;
use EMS\CoreBundle\Form\Form\ReorderType;

/**
 * Wysiwyg controller.
 *
 * @Route("/wysiwyg-profile")
 */
class WysiwygController extends AppController
{
	
	
	
    /**
     * Lists all Wysiwyg entities.
     *
     * @Route("/", name="ems_wysiwyg_index")
     * @Method({"GET", "POST"})
     */
    public function indexAction(Request $request)
    {
    	
    	$data = [];
    	$form = $this->createForm(ReorderType::class, $data, [
    	]);
    	$form->handleRequest($request);
    	
    	if ($form->isSubmitted()) {
    		$order = json_decode($form->getData()['items'], true);
    		$i = 1;
    		foreach ($order as $id){
	    		$profile = $this->getWysiwygProfileService()->get($id);
	    		$profile->setOrderKey($i++);
	    		
	    		$this->getWysiwygProfileService()->saveProfile($profile);
    		}
    		return $this->redirectToRoute('ems_wysiwyg_index');
    	}
    	
    	
        return $this->render('EMSCoreBundle:wysiwygprofile:index.html.twig', array(
        		'profiles' => $this->getWysiwygProfileService()->getProfiles(),
        		'form' => $form->createView(),
        ));
    }
    

    /**
     * Creates a new WYSIWYG profile entity.
     *
     * @Route("/new", name="ems_wysiwyg_profile_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $profile = new WysiwygProfile();
        
        $form = $this->createForm(WysiwygProfileType::class, $profile, [
        		'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()){
        	$data = json_decode($profile->getConfig(), true);
        	if(json_last_error()){
        		$form->get('config')->addError(New FormError($this->getTranslator()->trans('Format not valid: %msg%', ['%msg%'=>json_last_error_msg()], 'EMSCoreBundle')));
        	}
        	else {
        		$profile->setOrderKey(100+count($this->getWysiwygProfileService()->getProfiles()));
        		$this->getWysiwygProfileService()->saveProfile($profile);
        		return $this->redirectToRoute('ems_wysiwyg_index');
        	}	 
        }

        return $this->render('EMSCoreBundle:wysiwygprofile:new.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing WysiwygProfile entity.
     *
     * @Route("/edit/{id}", name="ems_wysiwyg_profile_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, WysiwygProfile $profile)
    {
    	$form= $this->createForm(WysiwygProfileType::class, $profile);
    	$form->handleRequest($request);

    	if ($form->isSubmitted()) {
    		
    		
    		if($form->get('remove') && $form->get('remove')->isClicked()){
    			$this->getWysiwygProfileService()->remove($profile);
    			return $this->redirectToRoute('ems_wysiwyg_index');
    		}
    		
    		if($form->isValid()) {
	    		
	    		$data = json_decode($profile->getConfig(), true);
	    		if(json_last_error()){
	    			$form->get('config')->addError(New FormError($this->getTranslator()->trans('Format not valid: %msg%', ['%msg%'=>json_last_error_msg()], 'EMSCoreBundle')));    				
	    		}
	   			else {
			       	$this->getWysiwygProfileService()->saveProfile($profile);		
			        return $this->redirectToRoute('ems_wysiwyg_index');
		    	}
    			
    		}
        }
        
        return $this->render('EMSCoreBundle:wysiwygprofile:edit.html.twig', array(
        		'form' => $form->createView(),
        ));
    }
}
