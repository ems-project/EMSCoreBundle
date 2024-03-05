<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Query;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\QueryServiceInterface;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\Json;

class PublishedRevisionsQueryService implements QueryServiceInterface
{
    public function __construct(
        private readonly Registry $doctrine,
        private readonly ContentTypeService $contentTypeService,
        private readonly RevisionService $revisionService
    ) {
    }

    public function isQuerySortable(): bool
    {
        return false;
    }

    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $qb = $this->createQueryBuilder($context)
            ->addSelect("CONCAT(c.name, ':', p.ouuid) AS ems_link")
            ->addSelect('c.name as content_type_name')
            ->addSelect('c.singularname as content_type_label')
            ->addSelect('c.fields as fields')
            ->addSelect('p.ouuid as ouuid')
            ->addSelect('p.raw_data published_raw')
            ->addSelect('p.finalized_by as published_finalized_by')
            ->addSelect('p.finalized_date as published_finalized_date')
            ->addSelect('r.finalized_by as default_finalized_by')
            ->addSelect('r.finalized_date as default_finalized_date')
            ->setFirstResult($from)
            ->setMaxResults($size);

        if ('content_type_label' === $orderField) {
            $qb->orderBy('c.singularname', $orderDirection);
        }

        $results = $qb->executeQuery()->fetchAllAssociative();

        foreach ($results as &$result) {
            [$contentTypeName, $ouuid] = [$result['content_type_name'], $result['ouuid']];

            $publishedDocument = Document::fromData($contentTypeName, $ouuid, Json::decode($result['published_raw']));
            $result['label'] = $this->revisionService->display($publishedDocument);
        }

        return $results;
    }

    public function countQuery(string $searchValue = '', mixed $context = null): int
    {
        return (int) $this->createQueryBuilder($context ?? [])->select('count(p.id)')->fetchOne();
    }

    /**
     * @param array{'environment': Environment, 'exclude_ouuids'?: string[]} $context
     */
    private function createQueryBuilder(mixed $context = null): QueryBuilder
    {
        if (!isset($context['environment'])) {
            throw new \RuntimeException('Missing environment');
        }
        $environment = $context['environment'];
        $excludeOuuids = $context['exclude_ouuids'] ?? [];

        $contentTypes = $this->contentTypeService->getAllGrantedForPublication();
        $contentTypeIds = \array_map(static fn (ContentType $contentType) => $contentType->getId(), $contentTypes);

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        $qb = $em->getConnection()->createQueryBuilder();

        $qb = $qb
            ->from('revision', 'p')
            ->join('p', 'content_type', 'c', 'p.content_type_id = c.id')
            ->join('p', 'environment_revision', 'er', 'er.revision_id = p.id')
            ->join('p', 'revision', 'r', 'r.end_time is null and r.ouuid = p.ouuid')
            ->andWhere($qb->expr()->eq('er.environment_id', ':environment_id'))
            ->andWhere($qb->expr()->in('c.id', ':content_type_ids'))
            ->setParameter('environment_id', $environment->getId())
            ->setParameter('content_type_ids', $contentTypeIds, ArrayParameterType::INTEGER);

        if (\count($excludeOuuids) > 0) {
            $qb
                ->andWhere($qb->expr()->notIn('p.ouuid', ':exclude_ouuids'))
                ->setParameter('exclude_ouuids', $excludeOuuids, ArrayParameterType::STRING);
        }

        return $qb;
    }
}
