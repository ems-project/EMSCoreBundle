<?php

namespace EMS\CoreBundle\Repository;

/**
 * DataFieldRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class SearchFieldOptionRepository extends \Doctrine\ORM\EntityRepository
{
    
    public function findAll(){
        return parent::findBy([], ['orderKey' => 'asc']);
    }
}
