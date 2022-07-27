<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Entity;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use EMS\CoreBundle\Entity\Revision;

final class Paginator implements \IteratorAggregate, \Countable
{
    private QueryBuilder $queryBuilder;
    private Configuration $config;
    private int $pageMaxResult;
    private bool $disableLogging = false;
    private ?SQLLogger $sqlLogger;

    public function __construct(QueryBuilder $queryBuilder, int $pageMaxResult)
    {
        $this->queryBuilder = $queryBuilder;
        $this->queryBuilder->setMaxResults($pageMaxResult);
        $this->pageMaxResult = $pageMaxResult;
        $this->config = $queryBuilder->getEntityManager()->getConnection()->getConfiguration();
    }

    public function count(): int
    {
        $this->actionStart();
        $count = $this->getPaginator()->count();
        $this->actionEnd();

        return $count;
    }

    public function disableLogging()
    {
        $this->disableLogging = true;
    }

    /**
     * @return Revision[]
     */
    public function getIterator(): \Generator
    {
        $this->actionStart();
        $page = 0;
        $paginator = $this->getPaginator($page);

        do {
            foreach ($paginator as $result) {
                yield $result;
            }

            $this->queryBuilder->getEntityManager()->clear();

            $paginator = $this->getPaginator(++$page);
            $iterator = $paginator->getIterator();
        } while ($iterator instanceof \ArrayIterator && $iterator->count());

        $this->actionEnd();
    }

    private function getPaginator(?int $page = 0): DoctrinePaginator
    {
        $this->queryBuilder->setFirstResult($page * $this->pageMaxResult);

        return new DoctrinePaginator($this->queryBuilder);
    }

    private function actionStart(): void
    {
        if ($this->disableLogging) {
            $this->sqlLogger = $this->config->getSQLLogger();
            $this->config->setSQLLogger(null);
        }
    }

    private function actionEnd(): void
    {
        if ($this->sqlLogger) {
            $this->config->setSQLLogger($this->sqlLogger);
            $this->sqlLogger = null;
        }
    }
}
