<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use EMS\CoreBundle\Entity\Sequence;
use EMS\CoreBundle\Exception\SequenceException;

/**
 * @extends ServiceEntityRepository<Sequence>
 *
 * @method Sequence|null find($id, $lockMode = null, $lockVersion = null)
 * @method Sequence|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method Sequence[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
class SequenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sequence::class);
    }

    /**
     * Get the next value of a sequence for a sequence name.
     *
     * @param string $name
     *
     * @return int
     */
    public function nextValue($name)
    {
        $qb = $this->createQueryBuilder('s');
        $q = $qb->select('s.value', 's.version', 's.id')
            ->where($qb->expr()->eq('s.name', ':name'))
            ->setParameter('name', $name)
            ->getQuery();

        $result = $q->execute();

        $this->getEntityManager()->beginTransaction();

        $out = 0;
        if (empty($result)) {
            $sequence = new Sequence($name);
            $out = $sequence->getValue();
            $this->getEntityManager()->persist($sequence);
            $this->getEntityManager()->flush();
        } else {
            $item = $result[0];
            $q = $qb->update()
                ->set('s.version', 's.version + 1')
                ->set('s.value', 's.value + 1')
                ->where($qb->expr()->eq('s.name', ':name'))
                ->andWhere($qb->expr()->eq('s.version', ':version'))
                ->setParameter('name', $name)
                ->setParameter('version', $item['version'])
                ->getQuery();

            $out = $item['value'] + 1;

            if (1 != $q->execute()) {
                throw new SequenceException('An error has been detected with the sequence '.$name);
            }
        }
        $this->getEntityManager()->commit();

        return $out;
    }
}
