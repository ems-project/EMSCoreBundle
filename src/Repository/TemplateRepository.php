<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Template;

class TemplateRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Template::class);
    }

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

    /**
     * @return Template[]
     */
    public function getAll(ContentType $contentType): array
    {
        return $this->findBy(['contentType' => $contentType], ['orderKey' => 'ASC']);
    }

    public function counter(ContentType $contentType): int
    {
        return parent::count(['contentType' => $contentType]);
    }

    public function delete(Template $template): void
    {
        $this->getEntityManager()->remove($template);
        $this->getEntityManager()->flush();
    }

    public function create(Template $template): void
    {
        $this->getEntityManager()->persist($template);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return Template[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('template');
        $queryBuilder->where('template.id IN (:ids)')
            ->setParameter('ids', $ids);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return Template[]
     */
    public function get(ContentType $contentType, int $from, int $size): array
    {
        $query = $this->createQueryBuilder('t')
            ->where('t.contentType = :contentType')
            ->orderBy('t.orderKey', 'ASC')
            ->setParameter('contentType', $contentType)
            ->setFirstResult($from)
            ->setMaxResults($size)
            ->getQuery();

        return $query->execute();
    }
}
