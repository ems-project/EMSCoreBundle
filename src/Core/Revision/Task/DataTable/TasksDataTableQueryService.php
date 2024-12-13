<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task\DataTable;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CoreBundle\Core\Revision\Task\TaskStatus;
use EMS\CoreBundle\Service\QueryServiceInterface;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Standard\Json;

class TasksDataTableQueryService implements QueryServiceInterface
{
    private const SEARCH_COLUMNS = ['t.title', 't.description'];
    private const ORDER_COLUMNS = [
        'task_title' => ['t.title'],
        'task_modified' => ['t.modified'],
        'task_status' => ['t.status'],
        'task_requester' => ['t.created_by'],
        'task_assignee' => ['t.assignee'],
        'task_deadline' => ['t.deadline', 't.delay'],
        'revision_version_next_tag' => ['r.version_next_tag'],
    ];

    public function __construct(
        private readonly Registry $doctrine,
        private readonly UserService $userService,
        private readonly RevisionService $revisionService,
    ) {
    }

    public function isSortable(): bool
    {
        return false;
    }

    /**
     * @param ?TasksDataTableContext $context
     */
    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $qb = $this->createQueryBuilder($context, $searchValue)
            ->addSelect('t.id as task_id')
            ->addSelect('t.title as task_title')
            ->addSelect('t.status as task_status')
            ->addSelect('t.assignee as task_assignee')
            ->addSelect('t.created_by as task_requester')
            ->addSelect('t.deadline as task_deadline')
            ->addSelect('t.delay as task_delay')
            ->addSelect('t.modified as task_modified')
            ->addSelect('r.ouuid as revision_ouuid')
            ->addSelect('r.raw_data as revision_raw_data')
            ->addSelect('r.version_next_tag as revision_version_next_tag')
            ->addSelect('c.name as content_type_name')
            ->setMaxResults($size)
            ->setFirstResult($from);

        if ($orderField && isset(self::ORDER_COLUMNS[$orderField])) {
            foreach (self::ORDER_COLUMNS[$orderField] as $orderColumn) {
                $qb->orderBy($orderColumn, $orderDirection);
            }
        }

        $results = $qb->executeQuery()->fetchAllAssociative();

        foreach ($results as &$result) {
            $status = TaskStatus::from($result['task_status']);
            $result['task_status_class_icon'] = $status->getCssClassIcon();
            $result['task_status_class_text'] = $status->getCssClassText();

            [$contentTypeName, $ouuid] = [$result['content_type_name'], $result['revision_ouuid']];
            $document = Document::fromData($contentTypeName, $ouuid, Json::decode($result['revision_raw_data']));
            $result['revision_label'] = $this->revisionService->display($document);
        }

        return $results;
    }

    /**
     * @param ?TasksDataTableContext $context
     */
    public function countQuery(string $searchValue = '', mixed $context = null): int
    {
        return (int) $this->createQueryBuilder($context, $searchValue)->select('count(t.id)')->fetchOne();
    }

    private function createQueryBuilder(?TasksDataTableContext $context, string $searchValue = ''): QueryBuilder
    {
        if (null === $context) {
            throw new \RuntimeException('Missing context');
        }

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        $qb = $em->getConnection()->createQueryBuilder();

        $qb = $qb
            ->from('task', 't')
            ->join('t', 'revision', 'r', 't.revision_ouuid = r.ouuid and r.end_time is null and r.deleted is false')
            ->join('r', 'content_type', 'c', 'r.content_type_id = c.id');

        $user = $this->userService->getCurrentUser();

        switch ($context->tab) {
            case TasksDataTableContext::TAB_USER:
                $qb
                    ->andWhere($qb->expr()->eq('t.assignee', ':username'))
                    ->setParameter('username', $user->getUsername());
                break;
            case TasksDataTableContext::TAB_REQUESTER:
                $qb
                    ->andWhere($qb->expr()->eq('t.created_by', ':username'))
                    ->setParameter('username', $user->getUsername());
                break;
        }

        if ('' !== $searchValue) {
            $or = \array_map(static fn (string $column) => $qb->expr()->like($column, ':term'), self::SEARCH_COLUMNS);
            $qb
                ->andWhere($qb->expr()->or(...$or))
                ->setParameter('term', '%'.$searchValue.'%');
        }

        $filters = [
            'status' => ['t.status', $context->filters->status],
            'assignee' => ['t.assignee', $context->filters->assignee],
            'requester' => ['t.created_by', $context->filters->requester],
            'version_next' => ['r.version_next_tag', $context->filters->getVersionNextTag()],
        ];

        foreach ($filters as $name => [$column, $values]) {
            $expressions = [];
            if (\in_array(null, $values, true)) {
                $expressions[] = $qb->expr()->isNull($column);
                $values = \array_filter($values);
            }

            if (\count($values) > 0) {
                $expressions[] = $qb->expr()->in($column, ':filter_'.$name);
                $qb->setParameter('filter_'.$name, $values, ArrayParameterType::STRING);
            }

            if (1 === \count($expressions)) {
                $qb->andWhere(...$expressions);
            } elseif (\count($expressions) > 1) {
                $qb->andWhere($qb->expr()->or(...$expressions));
            }
        }

        return $qb;
    }
}
