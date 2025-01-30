<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use EMS\SubmissionBundle\Entity\FormSubmissionFile;

/**
 * @extends ServiceEntityRepository<FormSubmissionFile>
 *
 * @method FormSubmissionFile|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 */
class FormSubmissionFileRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, FormSubmissionFile::class);
    }

    public function findOneBySubmission(string $submissionId, string $submissionFileId): ?FormSubmissionFile
    {
        $qb = $this->createQueryBuilder('f');
        $qb
            ->addSelect('s')
            ->join('f.formSubmission', 's')
            ->andWhere($qb->expr()->eq('f.id', ':submission_file_id'))
            ->andWhere($qb->expr()->eq('s.id', ':submission_id'))
            ->setParameters(new ArrayCollection([
                new Parameter('submission_file_id', $submissionFileId),
                new Parameter('submission_id', $submissionId),
            ]));

        return $qb->getQuery()->getOneOrNullResult();
    }
}
