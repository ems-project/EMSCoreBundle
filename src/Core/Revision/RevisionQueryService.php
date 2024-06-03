<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\QueryServiceInterface;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\Json;

class RevisionQueryService implements QueryServiceInterface
{
    public function __construct(
        private readonly Registry $doctrine,
        private readonly RevisionService $revisionService,
    ) {
    }

    public function isQuerySortable(): bool
    {
        return false;
    }

    /**
     * @param array{'content_type'?: ContentType, 'deleted'?: bool, 'current'?: bool}|null $context
     *
     * @return array<array<string, mixed>>
     */
    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $qb = $this->createQueryBuilder($context ?? [])
            ->addSelect('r.id as id')
            ->addSelect('r.modified as modified')
            ->addSelect('r.deleted_by as deleted_by')
            ->addSelect('r.ouuid as ouuid')
            ->addSelect('r.raw_data as raw_data')
            ->addSelect('c.id as content_type_id')
            ->addSelect('c.name as content_type_name')
            ->setMaxResults($size)
            ->setFirstResult($from);

        if ($orderField) {
            $qb->orderBy($orderField, $orderDirection);
        }

        $results = $qb->executeQuery()->fetchAllAssociative();

        foreach ($results as &$result) {
            [$contentTypeName, $ouuid] = [$result['content_type_name'], $result['ouuid']];
            $document = Document::fromData($contentTypeName, $ouuid, Json::decode($result['raw_data']));
            $result['revision_label'] = $this->revisionService->display($document);
        }

        return $results;
    }

    /**
     * @param array{'content_type'?: ContentType, 'deleted'?: bool, 'current'?: bool}|null $context
     */
    public function countQuery(string $searchValue = '', mixed $context = null): int
    {
        return (int) $this->createQueryBuilder($context ?? [])->select('count(r.id)')->fetchOne();
    }

    /**
     * @param array{'content_type'?: ContentType, 'deleted'?: bool, 'current'?: bool} $context
     */
    private function createQueryBuilder(array $context): QueryBuilder
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        $qb = $em->getConnection()->createQueryBuilder();

        $qb = $qb
            ->from('revision', 'r')
            ->join('r', 'content_type', 'c', 'r.content_type_id = c.id');

        foreach ($context as $key => $value) {
            match ($key) {
                'content_type' => $qb
                    ->andWhere($qb->expr()->eq('c.id', ':content_type_id'))
                    ->setParameter('content_type_id', $value->getId()),
                'current' => $qb->andWhere($qb->expr()->isNull('r.end_time')),
                'deleted' => $qb->andWhere($qb->expr()->eq('r.deleted', $qb->expr()->literal($value)))
            };
        }

        return $qb;
    }
}
