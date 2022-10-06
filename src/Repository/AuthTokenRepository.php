<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\UserInterface;

/**
 * @extends ServiceEntityRepository<AuthToken>
 *
 * @method AuthToken|null findOneBy(array $criteria, array $orderBy = null)
 */
class AuthTokenRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry, AuthToken::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function create(UserInterface $user): AuthToken
    {
        $authToken = new AuthToken($user);

        $this->entityManager->persist($authToken);
        $this->entityManager->flush();

        return $authToken;
    }
}
