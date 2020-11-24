<?php

namespace EMS\CoreBundle\Repository;

class TemplateRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * Retrieve all Template by a render_option defined and a ContentType Filter.
     *
     * @param string $option
     * @param array  $contentTypes
     *
     * @return mixed
     */
    public function findByRenderOptionAndContentType($option, $contentTypes = null)
    {
        $qb = $this->createQueryBuilder('t')
        ->select('t')
        ->where('t.renderOption = :option');

        if (null != $contentTypes) {
            $qb->andWhere('t.contentType IN (:cts)')
            ->setParameters(['option' => $option, 'cts' => $contentTypes]);
        } else {
            $qb->setParameter('option', $option);
        }

        $query = $qb->getQuery();

        $results = $query->getResult();

        return $results;
    }
}
