<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Repository\WysiwygProfileRepository;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Translation\TranslatorInterface;

class WysiwygProfileService {
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
	
	
	public function getProfiles(){
		$em = $this->doctrine->getManager();
		/**@var WysiwygProfileRepository */
		$repository = $em->getRepository('EMSCoreBundle:WysiwygProfile');
		
		$profiles = $repository->findAll();
		
		return $profiles;
	}
	
	/**
	 * 
	 * @param integer $id
	 * @return WysiwygProfile|NULL
	 */
	public function get($id){
		$em = $this->doctrine->getManager();
		/**@var WysiwygProfileRepository */
		$repository = $em->getRepository('EMSCoreBundle:WysiwygProfile');
		
		$profile = $repository->find($id);
		
		return $profile;
	}
	
	
	
	
	public function saveProfile(WysiwygProfile $profile){
		$em = $this->doctrine->getManager();
		$em->persist($profile);
		$em->flush();
		$this->session->getFlashBag()->add('notice', $this->translator->trans('Profile %name% has been updated', ['%name%' => $profile->getName()], 'EMSCoreBundle'));
	}
	
	
	public function remove(WysiwygProfile $profile){
		$em = $this->doctrine->getManager();
		$em->remove($profile);
		$em->flush();
		$this->session->getFlashBag()->add('notice', $this->translator->trans('Profile has been deleted', [], 'EMSCoreBundle'));
	}
	

}