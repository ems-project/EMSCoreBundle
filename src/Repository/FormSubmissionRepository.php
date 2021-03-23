<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\FormSubmission;

class FormSubmissionRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, FormSubmission::class);
    }

    public function findById(string $id): ?FormSubmission
    {
        $submission = $this->findOneBy(['id' => $id]);

        return $submission instanceof FormSubmission ? $submission : null;
    }

    /**
     * @return FormSubmission[]
     */
    public function get(int $from, int $size): array
    {
        $qb = $this->createQueryBuilder('fs');
        $qb
            ->andWhere($qb->expr()->isNotNull('fs.data'))
            ->setFirstResult($from)
            ->setMaxResults($size)
            ->orderBy('fs.created', 'desc');

        return $qb->getQuery()->execute();
    }

    /**
     * @return FormSubmission[]
     */
    public function findAllUnprocessed(): array
    {
        $qb = $this->createQueryBuilder('fs');
        $qb
            ->andWhere($qb->expr()->isNotNull('fs.data'))
            ->orderBy('fs.created', 'desc');

        return $qb->getQuery()->getArrayResult();
    }

    public function countAllUnprocessed(): int
    {
        $qb = $this->createQueryBuilder('fs');
        $qb->select('count(fs.data)');

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function removeAllOutdatedSubmission(): int
    {
        $outdatedSubmissions = $this->createQueryBuilder('fs')
            ->andWhere('fs.expireDate < :today')
            ->setParameter('today', new \DateTime())
            ->getQuery()
            ->getResult();

        $removedCount = 0;

        foreach ($outdatedSubmissions as $submission) {
            $this->remove($submission);
            ++$removedCount;
        }

        $this->flush();

        return $removedCount;
    }

    /**
     * @return FormSubmission[]
     */
    public function findFormSubmissions(?string $formInstance = null): array
    {
        $qb = $this->createQueryBuilder('fs');

        if ($formInstance) {
            $qb->andWhere('fs.name = :name')
            ->setParameter('name', $formInstance);
        }

        $qb
            ->andWhere($qb->expr()->isNotNull('fs.data'))
            ->orderBy('fs.created', 'desc');

        return $qb->getQuery()->getArrayResult();
    }

    public function persist(FormSubmission $formSubmission): void
    {
        $this->_em->persist($formSubmission);
    }

    public function save(FormSubmission $formSubmission): void
    {
        $this->_em->persist($formSubmission);
        $this->_em->flush();
    }

    public function remove(FormSubmission $formSubmission): void
    {
        $this->_em->remove($formSubmission);
    }

    public function flush(): void
    {
        $this->_em->flush();
    }
}
