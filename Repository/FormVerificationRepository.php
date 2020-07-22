<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\FormVerification;

class FormVerificationRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, FormVerification::class);
    }

    public function create(FormVerification $verification): FormVerification
    {
        $exists = $this->get($verification->getValue());

        if (null !== $exists) {
            return $exists;
        }

        $this->_em->persist($verification);
        $this->_em->flush();

        return $verification;
    }

    /**
     * Get a NOT expired form verification, if hit we reset the expiration date.
     */
    public function get(string $value): ?FormVerification
    {
        $qb = $this->createQueryBuilder('fv');
        $qb
            ->andWhere($qb->expr()->eq('fv.value', ':value'))
            ->andWhere($qb->expr()->gte('fv.expirationDate', ':now'))
            ->setParameters([
                'value' => $value,
                'now' => new \DateTimeImmutable()
            ]);

        $formVerification = $qb->getQuery()->getOneOrNullResult();

        if (null !== $formVerification) {
            $this->updateExpirationDate($formVerification);
        }

        return $formVerification;
    }

    private function updateExpirationDate(FormVerification $formVerification): void
    {
        $formVerification->updateExpirationDate();
        $this->_em->persist($formVerification);
        $this->_em->flush();
    }
}
