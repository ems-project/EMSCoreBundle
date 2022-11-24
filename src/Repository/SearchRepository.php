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
}
