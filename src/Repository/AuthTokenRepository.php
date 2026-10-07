<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\UserInterface;

/**
 * @extends ServiceEntityRepository<AuthToken>
 *
 * @method AuthToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method AuthToken|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method AuthToken[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
class AuthTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthToken::class);
    }

    public function create(UserInterface $user): AuthToken
    {
        $authToken = new AuthToken($user);

        $this->getEntityManager()->persist($authToken);
        $this->getEntityManager()->flush();

        return $authToken;
    }

    public function save(AuthToken $authToken): void
    {
        $this->getEntityManager()->persist($authToken);
        $this->getEntityManager()->flush();
    }
}
