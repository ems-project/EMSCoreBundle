<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\Sequence;
use EMS\CoreBundle\Exception\SequenceException;

/**
 * @extends EntityRepository<Sequence>
 */
class SequenceRepository extends EntityRepository
{
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

        $this->_em->beginTransaction();

        $out = 0;
        if (empty($result)) {
            $sequence = new Sequence($name);
            $out = $sequence->getValue();
            $this->_em->persist($sequence);
            $this->_em->flush();
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
        $this->_em->commit();

        return $out;
    }
}
