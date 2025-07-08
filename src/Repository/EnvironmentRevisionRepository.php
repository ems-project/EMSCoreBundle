<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\EnvironmentRevision;
use EMS\CoreBundle\Entity\Revision;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<EnvironmentRevision>
 *
 * @method EnvironmentRevision|null find($id, $lockMode = null, $lockVersion = null)
 * @method EnvironmentRevision|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method EnvironmentRevision[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
class EnvironmentRevisionRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, EnvironmentRevision::class);
    }

    /**
     * @return array<int, int>
     */
    public function countDocumentsByEnvironments(bool $deleted = false, bool $managed = true): array
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->select('er.environment_id', 'count(er.revision_id)')
            ->from('environment_revision', 'er')
            ->join('er', 'revision', 'r', 'r.id = er.revision_id')
            ->join('er', 'environment', 'e', 'e.id = er.environment_id')
            ->andWhere($qb->expr()->eq('e.managed', ':managed'))
            ->setParameter('managed', $managed, ParameterType::BOOLEAN)
            ->groupBy('er.environment_id');

        if ($deleted) {
            $qb
                ->andWhere($qb->expr()->isNotNull('er.deleted'))
                ->andWhere($qb->expr()->isNull('r.end_time'))
                ->andWhere($qb->expr()->eq('r.deleted', ':deleted'))
                ->setParameter('deleted', $deleted, ParameterType::BOOLEAN);
        } else {
            $qb->andWhere($qb->expr()->isNull('er.deleted'));
        }

        /** @var array<int, int> $result */
        $result = $qb->fetchAllKeyValue();

        return $result;
    }

    public function delete(Revision $revision, Environment $environment, string $username): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare('update environment_revision set deleted = :now, deleted_by = :username  where environment_id = :envId and revision_id = :revId and deleted is null');
        $stmt->bindValue('envId', $environment->getId());
        $stmt->bindValue('revId', $revision->getId());
        $stmt->bindValue('now', new \DateTime()->format('Y-m-d H:i:s'));
        $stmt->bindValue('username', $username);

        return (int) $stmt->executeStatement();
    }

    public function create(Revision $revision, Environment $environment, string $username): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare('insert into environment_revision (id, environment_id, revision_id, created,  created_by, deleted, deleted_by) VALUES(:id, :envId, :revId, :now, :username, null, null)');
        $stmt->bindValue('id', Uuid::uuid4()->toString());
        $stmt->bindValue('envId', $environment->getId());
        $stmt->bindValue('revId', $revision->getId());
        $stmt->bindValue('now', new \DateTime()->format('Y-m-d H:i:s'));
        $stmt->bindValue('username', $username);

        return (int) $stmt->executeStatement();
    }
}
