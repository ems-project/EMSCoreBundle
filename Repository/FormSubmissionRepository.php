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
     * @param string $formInstance
     * @return FormSubmission[]
     */
    public function findFormInstanceSubmissions(string $formInstance): array
    {
        $qb = $this->createQueryBuilder('fs');
        $qb
            ->andWhere($qb->expr()->isNotNull('fs.data'))
            ->andWhere('fs.name = :name')
            ->orderBy('fs.created', 'desc')
            ->setParameter('name', $formInstance);

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @return FormSubmission[]
     */
    public function findAllFormSubmissions(): array
    {
        $qb = $this->createQueryBuilder('fs');
        $qb
            ->andWhere($qb->expr()->isNotNull('fs.data'))
            ->orderBy('fs.created', 'desc');

        return $qb->getQuery()->getArrayResult();
    }

    public function save(FormSubmission $formSubmission): void
    {
        $this->_em->persist($formSubmission);
        $this->_em->flush();
    }
}
