<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Core\Security\Canonicalizer;
use EMS\CoreBundle\Core\User\UserContextDTO;
use EMS\CoreBundle\Core\User\UserList;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
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

    /**
     * @return array{count: int, results: iterable<User>}
     */
    public function countFindAll(?string $email = null): array
    {
        $qb = $this->createQueryBuilder('u');

        if ($email) {
            $qb
                ->andWhere($qb->expr()->like('u.email', ':email'))
                ->setParameter('email', $email);
        }

        return [
            'count' => (int) $qb->select('count(u.id)')->getQuery()->getSingleScalarResult(),
            'results' => $qb->select('u')->getQuery()->toIterable(),
        ];
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
        $field = \preg_match('/^.+\@\S+\.\S+$/', $usernameOrEmail) ? 'emailCanonical' : 'usernameCanonical';

        return $this->findOneBy([$field => Canonicalizer::canonicalize($usernameOrEmail)]);
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

    #[\Override]
    public function findForRoleAndCircles(string $role, array $circles): array
    {
        $qb = $this->createQueryBuilder('u');
        $resultSet = $qb
            ->andWhere($qb->expr()->eq('u.enabled', $qb->expr()->literal(true)))
            ->getQuery()
            ->getResult();

        $resultSet = \array_filter($resultSet, static fn (User $user) => \in_array($role, $user->getRoles()));

        if ([] !== $circles) {
            /** @var UserInterface $user */
            foreach ($resultSet as $idx => $user) {
                if (empty(\array_intersect($circles, $user->getCircles()))) {
                    unset($resultSet[$idx]);
                }
            }
        }

        return $resultSet;
    }

    #[\Override]
    public function getUsersEnabled(): UserList
    {
        $resultSet = $this->findBy([
            'enabled' => true,
        ]);

        return new UserList($resultSet);
    }

    public function countUsers(string $searchValue, ?UserContextDTO $context = null): int
    {
        $qb = $this->createQueryBuilder('user');
        $qb->select('count(user.id)');
        $this->addSearchFilters($qb, $searchValue);

        return (int) $this->getQuery($qb, $context)->getSingleScalarResult();
    }

    /**
     * @return User[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, ?UserContextDTO $context = null): array
    {
        $qb = $this->createQueryBuilder('user');
        $qb->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['username', 'displayName', 'emailNotification', 'email', 'enabled', 'lastLogin'])) {
            $qb->orderBy(\sprintf('user.%s', $orderField), $orderDirection);
        }

        return $this->getQuery($qb, $context)->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if ('' !== $searchValue) {
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

    /**
     * @return Query<null, User>
     */
    private function getQuery(QueryBuilder $qb, ?UserContextDTO $context): Query
    {
        if (null == $context || null == $context->groupId) {
            return $qb->getQuery();
        }
        if ($context->inGroup) {
            $inGroup = $qb->expr()->eq('user.group', ':userGroup');
            $qb->andWhere($inGroup);
        } else {
            $inGroup = $qb->expr()->neq('user.group', ':userGroup');
            $isNull = $qb->expr()->isNull('user.group');
            $qb->orWhere($inGroup);
            $qb->orWhere($isNull);
        }
        $qb->setParameter('userGroup', $context->groupId);

        return $qb->getQuery();
    }

    public function remove(UserInterface $user): void
    {
        $this->getEntityManager()->remove($user);
        $this->getEntityManager()->flush();
    }
}
