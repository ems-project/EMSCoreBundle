<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\Form\Search;

/**
 * @extends ServiceEntityRepository<Search>
 *
 * @method Search[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SearchRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Search::class);
    }

    /**
     * @return Search[]
     */
    public function getByUsername(string $username): array
    {
        return $this->findBy(['user' => $username]);
    }

    /**
     * @return Search[]
     */
    public function getAll(): array
    {
        return $this->findBy([]);
    }

    public function remove(Search $search): void
    {
        $this->_em->remove($search);
        $this->_em->flush();
    }

    public function save(Search $search): void
    {
        $this->_em->persist($search);
        $this->_em->flush();
    }
}
