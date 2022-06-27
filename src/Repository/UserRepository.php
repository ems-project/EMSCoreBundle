<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Core\Security\Canonicalizer;
use EMS\CoreBundle\Core\User\UserList;
use EMS\CoreBundle\Entity\User;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class UserRepository extends ServiceEntityRepository implements UserRepositoryInterface
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findUserByUsernameOrThrowException(string $username): User
    {
        $user = $this->findOneBy(['usernameCanonical' => Canonicalizer::canonicalize($username)]);

        if (!$user) {
            throw new \InvalidArgumentException(\sprintf('User identified by "%s" username does not exist.', $username));
        }

        return $user;
    }

    public function findUserByUsernameOrEmail(string $usernameOrEmail): ?User
    {
        if (\preg_match('/^.+\@\S+\.\S+$/', $usernameOrEmail)) {
            $user = $this->findOneBy(['emailCanonical' => Canonicalizer::canonicalize($usernameOrEmail)]);

            if (null !== $user) {
                return $user;
            }
        }

        return $this->findOneBy(['usernameCanonical' => Canonicalizer::canonicalize($usernameOrEmail)]);
    }

    public function search(string $search): ?User
    {
        $qb = $this->createQueryBuilder('u');
        $qb
            ->setParameter('search', $search)
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('u.displayName', ':search'),
                    $qb->expr()->eq('u.username', ':search'),
                    $qb->expr()->eq('u.usernameCanonical', ':search'),
                    $qb->expr()->eq('u.email', ':search'),
                )
            );

        $result = $qb->getQuery()->getResult();

        return isset($result[0]) && $result[0] instanceof User ? $result[0] : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findForRoleAndCircles(string $role, array $circles): array
    {
        $resultSet = $this->createQueryBuilder('u')
            ->where('u.roles like :role')
            ->andWhere('u.enabled = :enabled')
            ->setParameters([
                    'role' => '%"'.$role.'"%',
                    'enabled' => true,
            ])->getQuery()->getResult();

        if (!empty($circles)) {
            /** @var \EMS\CoreBundle\Entity\UserInterface $user */
            foreach ($resultSet as $idx => $user) {
                if (empty(\array_intersect($circles, $user->getCircles()))) {
                    unset($resultSet[$idx]);
                }
            }
        }

        return $resultSet;
    }

    public function getUsersEnabled(): UserList
    {
        $resultSet = $this->findBy([
            'enabled' => true,
        ]);

        return new UserList($resultSet);
    }

    public function countUsers(string $searchValue): int
    {
        $qb = $this->createQueryBuilder('user');
        $qb->select('count(user.id)');
        $this->addSearchFilters($qb, $searchValue);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array<mixed>
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('user');
        $qb->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['username', 'displayName', 'emailNotification', 'email', 'enabled', 'lastLogin'])) {
            $qb->orderBy(\sprintf('user.%s', $orderField), $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('user.username', ':term'),
                $qb->expr()->like('user.displayName', ':term'),
                $qb->expr()->like('user.roles', ':term'),
                $qb->expr()->like('user.email', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
