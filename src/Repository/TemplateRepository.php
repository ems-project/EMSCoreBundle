<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Template;

/**
 * @extends ServiceEntityRepository<Template>
 *
 * @method Template[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TemplateRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Template::class);
    }

    /**
     * @param int[] $contentTypeIds
     *
     * @return Template[]
     */
    public function findByRenderOptionAndContentType(string $option, ?array $contentTypeIds = null): array
    {
        $qb = $this->createQueryBuilder('t')
        ->select('t')
        ->where('t.renderOption = :option');

        if (null != $contentTypeIds) {
            $qb->andWhere('t.contentType IN (:cts)')
            ->setParameters(['option' => $option, 'cts' => $contentTypeIds]);
        } else {
            $qb->setParameter('option', $option);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Template[]
     */
    public function getAll(ContentType $contentType): array
    {
        return $this->findBy(['contentType' => $contentType], ['orderKey' => 'ASC']);
    }

    public function counter(string $searchValue, ContentType $contentType): int
    {
        $qb = $this->createQueryBuilder('t');
        $qb->select('count(t.id)');
        $this->addSearchFilters($qb, $contentType, $searchValue);

        try {
            return \intval($qb->getQuery()->getSingleScalarResult());
        } catch (NonUniqueResultException $e) {
            return 0;
        }
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
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, ContentType $contentType): array
    {
        $qb = $this->createQueryBuilder('t')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $contentType, $searchValue);

        if (\in_array($orderField, ['name', 'render_option', 'public'])) {
            $qb->orderBy(\sprintf('t.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('t.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, ContentType $contentType, string $searchValue): void
    {
        $qb->where('t.contentType = :contentType')
            ->setParameter(':contentType', $contentType);
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('t.name', ':term'),
                $qb->expr()->like('t.renderOption', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }

    public function getById(string $id): Template
    {
        $action = $this->find($id);
        if (!$action instanceof Template) {
            throw new \RuntimeException('Unexpected action type');
        }

        return $action;
    }
}
