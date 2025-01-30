<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\FieldType;

/**
 * @extends ServiceEntityRepository<FieldType>
 */
class FieldTypeRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, FieldType::class);
    }

    public function save(FieldType $field): void
    {
        $this->getEntityManager()->persist($field);
        $this->getEntityManager()->flush();
    }
}
