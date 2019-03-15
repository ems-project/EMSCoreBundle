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
use EMS\CoreBundle\Entity\WysiwygStylesSet;
use EMS\CoreBundle\Form\Form\WysiwygStylesSetType;
use EMS\CoreBundle\Form\Form\ReorderBisType;

/**
 * Wysiwyg controller.
 *
 * @Route("/wysiwyg-options")
 */
class WysiwygController extends AppController
{
    
    
    
    /**
     * Lists all Wysiwyg options.
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
            foreach ($order as $id) {
                $profile = $this->getWysiwygProfileService()->get($id);
                $profile->setOrderKey($i++);
                
                $this->getWysiwygProfileService()->saveProfile($profile);
            }
            return $this->redirectToRoute('ems_wysiwyg_index');
        }
        
        $dataStylesSet = [];
        $formStylesSet= $this->createForm(ReorderBisType::class, $dataStylesSet, [
        ]);
        $formStylesSet->handleRequest($request);
        
        if ($formStylesSet->isSubmitted()) {
            $order = json_decode($formStylesSet->getData()['items'], true);
            $i = 1;
            foreach ($order as $id) {
                $stylesSet= $this->getWysiwygStylesSetService()->get($id);
                $stylesSet->setOrderKey($i++);
                
                $this->getWysiwygStylesSetService()->save($stylesSet);
            }
            return $this->redirectToRoute('ems_wysiwyg_index');
        }
        
        
        return $this->render('@EMSCore/wysiwygprofile/index.html.twig', array(
                'profiles' => $this->getWysiwygProfileService()->getProfiles(),
                'stylesSets' => $this->getWysiwygStylesSetService()->getStylesSets(),
                'form' => $form->createView(),
                'formStylesSet' => $formStylesSet->createView(),
        ));
    }
    

    /**
     * Creates a new WYSIWYG profile entity.
     *
     * @Route("/profile/new", name="ems_wysiwyg_profile_new")
     * @Method({"GET", "POST"})
     */
    public function newProfileAction(Request $request)
    {
        $profile = new WysiwygProfile();
        
        $form = $this->createForm(WysiwygProfileType::class, $profile, [
                'createform' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = json_decode($profile->getConfig(), true);
            if (json_last_error()) {
                $form->get('config')->addError(new FormError($this->getTranslator()->trans('Format not valid: %msg%', ['%msg%'=>json_last_error_msg()], 'EMSCoreBundle')));
            } else {
                $profile->setOrderKey(100+count($this->getWysiwygProfileService()->getProfiles()));
                $this->getWysiwygProfileService()->saveProfile($profile);
                return $this->redirectToRoute('ems_wysiwyg_index');
            }
        }

        return $this->render('@EMSCore/wysiwygprofile/new.html.twig', array(
            'form' => $form->createView(),
        ));
    }
    
    
    /**
     * Creates a new WYSIWYG Styles Set entity.
     *
     * @Route("/styles-set/new", name="ems_wysiwyg_styles_set_new")
     * @Method({"GET", "POST"})
     */
    public function newStylesSetAction(Request $request)
    {
        $stylesSet = new WysiwygStylesSet();
        
        $form = $this->createForm(WysiwygStylesSetType::class, $stylesSet, [
                'createform' => true,
        ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = json_decode($stylesSet->getConfig(), true);
            if (json_last_error()) {
                $form->get('config')->addError(new FormError($this->getTranslator()->trans('Format not valid: %msg%', ['%msg%'=>json_last_error_msg()], 'EMSCoreBundle')));
            } else {
                $stylesSet->setOrderKey(100+count($this->getWysiwygStylesSetService()->getStylesSets()));
                $this->getWysiwygStylesSetService()->save($stylesSet);
                return $this->redirectToRoute('ems_wysiwyg_index');
            }
        }
        
        return $this->render('@EMSCore/wysiwyg_styles_set/new.html.twig', array(
                'form' => $form->createView(),
        ));
    }
    
    /**
     * Displays a form to edit an existing WysiwygStylesSet entity.
     *
     * @Route("/styles-set/{id}", name="ems_wysiwyg_styles_set_edit")
     * @Method({"GET", "POST"})
     */
    public function editStylesSetAction(Request $request, WysiwygStylesSet $stylesSet)
    {
        $form= $this->createForm(WysiwygStylesSetType::class, $stylesSet);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            if ($form->get('remove') && $form->get('remove')->isClicked()) {
                $this->getWysiwygStylesSetService()->remove($stylesSet);
                return $this->redirectToRoute('ems_wysiwyg_index');
            }
            
            if ($form->isSubmitted() && $form->isValid()) {
                $data = json_decode($stylesSet->getConfig(), true);
                if (json_last_error()) {
                    $form->get('config')->addError(new FormError($this->getTranslator()->trans('Format not valid: %msg%', ['%msg%'=>json_last_error_msg()], 'EMSCoreBundle')));
                } else {
                    $this->getWysiwygStylesSetService()->save($stylesSet);
                    return $this->redirectToRoute('ems_wysiwyg_index');
                }
            }
        }
        
        return $this->render('@EMSCore/wysiwyg_styles_set/edit.html.twig', array(
                'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing WysiwygProfile entity.
     *
     * @Route("/profile/{id}", name="ems_wysiwyg_profile_edit")
     * @Method({"GET", "POST"})
     */
    public function editProfileAction(Request $request, WysiwygProfile $profile)
    {
        $form= $this->createForm(WysiwygProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->get('remove') && $form->get('remove')->isClicked()) {
                $this->getWysiwygProfileService()->remove($profile);
                return $this->redirectToRoute('ems_wysiwyg_index');
            }
            
            if ($form->isSubmitted() && $form->isValid()) {
                $data = json_decode($profile->getConfig(), true);
                if (json_last_error()) {
                    $form->get('config')->addError(new FormError($this->getTranslator()->trans('Format not valid: %msg%', ['%msg%'=>json_last_error_msg()], 'EMSCoreBundle')));
                } else {
                    $this->getWysiwygProfileService()->saveProfile($profile);
                    return $this->redirectToRoute('ems_wysiwyg_index');
                }
            }
        }
        
        return $this->render('@EMSCore/wysiwygprofile/edit.html.twig', array(
                'form' => $form->createView(),
        ));
    }
}
