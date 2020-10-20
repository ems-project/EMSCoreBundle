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

    /**
     * @return FormSubmission[]
     */
    public function getAllOutdatedSubmission()
    {
        $qb = $this->createQueryBuilder('fs');
        $qb->andWhere('fs.expireDate < :olderThan')
            ->setParameter('olderThan', new \DateTime());

        return $qb->getQuery()->getResult();
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
