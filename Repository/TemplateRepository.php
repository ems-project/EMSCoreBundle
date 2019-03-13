<?php

namespace EMS\CoreBundle\Repository;

use EMS\CoreBundle\Entity\Template;

/**
 * FieldTypeRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class TemplateRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     *  Retrieve all Template by a render_option defined and a ContentType Filter
     *
     *  @param String option, array $contentTypes
     */
    public function findByRenderOptionAndContentType($option, $contentTypes = null)
    {
        
        
        $qb = $this->createQueryBuilder('t')
        ->select('t')
        ->where('t.renderOption = :option');
                
        if ($contentTypes != null) {
            $qb->andWhere('t.contentType IN (:cts)')
            ->setParameters(array('option' => $option, 'cts' => $contentTypes));
        } else {
            $qb->setParameter('option', $option);
        }
    
        $query = $qb->getQuery();

        $results = $query->getResult();
    
        return $results;
    }
}
