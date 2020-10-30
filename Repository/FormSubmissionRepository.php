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
    public function findAllUnprocessed(): array
    {
        $qb = $this->createQueryBuilder('fs');
        $qb
            ->andWhere($qb->expr()->isNotNull('fs.data'))
            ->orderBy('fs.created', 'desc');

        return $qb->getQuery()->getArrayResult();
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
            $removedCount++;
        }

        $this->flush();

        return $removedCount;
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
