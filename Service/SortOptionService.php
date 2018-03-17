<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Repository\WysiwygProfileRepository;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Translation\TranslatorInterface;

class SortOptionService {
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
}