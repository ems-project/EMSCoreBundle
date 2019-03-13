<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\WysiwygStylesSet;
use EMS\CoreBundle\Repository\WysiwygStylesSetRepository;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Translation\TranslatorInterface;

class WysiwygStylesSetService {
    /**@var Registry $doctrine */
    private $doctrine;
    /**@var Session $session*/
    private $session;
    /**@var TranslatorInterface $translator */
    private $translator;
    
    public function __construct(Registry $doctrine, Session $session, TranslatorInterface $translator) {
        $this->doctrine = $doctrine;
        $this->session = $session;
        $this->translator= $translator;
    }
    
    
    public function getStylesSets(){
        $em = $this->doctrine->getManager();
        /**@var WysiwygStylesSetRepository */
        $repository = $em->getRepository('EMSCoreBundle:WysiwygStylesSet');
        
        $profiles = $repository->findAll();
        
        return $profiles;
    }
    
    /**
     * 
     * @param integer $id
     * @return WysiwygStylesSet|NULL
     */
    public function get($id){
        $em = $this->doctrine->getManager();
        /**@var WysiwygStylesSetRepository */
        $repository = $em->getRepository('EMSCoreBundle:WysiwygStylesSet');
        
        $profile = $repository->find($id);
        
        return $profile;
    }
    
    
    
    /**
     * 
     * @param WysiwygStylesSet $stylesSet
     */
    public function save(WysiwygStylesSet $stylesSet){
        $em = $this->doctrine->getManager();
        $em->persist($stylesSet);
        $em->flush();
        $this->session->getFlashBag()->add('notice', $this->translator->trans('Style set "%name%" has been updated', ['%name%' => $stylesSet->getName()], 'EMSCoreBundle'));
    }
    
    /**
     * 
     * @param WysiwygStylesSet $stylesSet
     */
    public function remove(WysiwygStylesSet $stylesSet){
        $name = $stylesSet->getName();
        $em = $this->doctrine->getManager();
        $em->remove($stylesSet);
        $em->flush();
        $this->session->getFlashBag()->add('notice', $this->translator->trans('Style set "%name% has been deleted', ['%name%' => $name], 'EMSCoreBundle'));
    }
    

}