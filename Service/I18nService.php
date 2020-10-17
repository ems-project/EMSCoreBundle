<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Common\Persistence\ObjectManager;
use EMS\CoreBundle\Entity\I18n;
use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Repository\I18nRepository;

class I18nService
{
    
    /**@var Registry $doctrine */
    private $doctrine;
    /** @var I18nRepository $repository */
    private $repository;
    /** @var ObjectManager $manager */
    private $manager;
    
    public function __construct(Registry $doctrine, I18nRepository $i18nRepository)
    {
        $this->doctrine = $doctrine;
        $this->manager = $this->doctrine->getManager();
        $this->repository = $i18nRepository;
    }

    public function count($filters = null)
    {
        $identifier = null;
        
        if ($filters != null && isset($filters['identifier']) && !empty($filters['identifier'])) {
            $identifier = $filters['identifier'];
        }
        return $this->repository->countWithFilter($identifier);
    }

    public function delete(I18n $i18n)
    {
        $this->manager->remove($i18n);
        $this->manager->flush();
    }
    
    /**
     * @return iterable|I18n[]
     */
    public function findAll($from, $limit, $filters = null): iterable
    {
        $identifier = null;
        
        if ($filters != null && isset($filters['identifier']) && !empty($filters['identifier'])) {
            $identifier = $filters['identifier'];
        }
        return $this->repository->findByWithFilter($limit, $from, $identifier);
    }
}
