<?php

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\I18n;
use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Repository\I18nRepository;
use Doctrine\ORM\EntityManager;


class I18nService {
	
	/**@var Registry $doctrine */
	private $doctrine;
	/** @var I18nRepository $repository */
	private $repository;
	/** @var EntityManager $manager */
	private $manager;
	
	public function __construct(Registry $doctrine)
	{
		$this->doctrine = $doctrine;
		$this->manager = $this->doctrine->getManager();
		$this->repository = $this->manager->getRepository('EMSCoreBundle:I18n');
	}

	public function count($filters = null) {
		$identifier = null;
		
		if($filters != null && isset($filters['identifier']) && !empty($filters['identifier'])) {
			$identifier = $filters['identifier'];
		}
		return $this->repository->count($identifier);
	}

	public function delete(I18n $i18n) {
		$this->manager->remove($i18n);
		$this->manager->flush();
	}
	
	/**
	 * Call to generate list of i18n keys
	 * 
	 * @return array Notification
	 */
	public function findAll($from, $limit, $filters = null) {
		$identifier = null;
		
		if($filters != null && isset($filters['identifier']) && !empty($filters['identifier'])) {
			$identifier = $filters['identifier'];
		}
		return $this->repository->findByWithFilter($limit, $from, $identifier);
	}
	
}