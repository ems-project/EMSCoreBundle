<?php

namespace EMS\CoreBundle\Service;

use Elastica\Aggregation\Terms;
use Elasticsearch\Endpoints\Cat\Indices;
use Elasticsearch\Endpoints\Indices\Alias\Get;
use Elasticsearch\Endpoints\Indices\Aliases\Update;
use EMS\CommonBundle\Elasticsearch\Client;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\ManagedAliasRepository;
use Psr\Log\LoggerInterface;

class AliasService
{
    private const COUNTER_AGGREGATION = 'counter_aggregation';
    /** @var EnvironmentRepository */
    private $envRepo;
    /** @var ManagedAliasRepository */
    private $managedAliasRepo;
    /** @var array<string, array{name: string, total: int, indexes: array, environment: string, managed: bool}> */
    private $aliases = [];
    /** @var array<array{name: string, count: int}> */
    private $orphanIndexes = [];
    /** @var bool */
    private $isBuild = false;
    /** @var Client */
    private $elasticaClient;
    /** @var ElasticaService */
    private $elasticaService;
    /** @var array<string, int> */
    private $counterIndexes;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger, Client $elasticaClient, EnvironmentRepository $environmentRepository, ManagedAliasRepository $managedAliasRepository, ElasticaService $elasticaService)
    {
        $this->counterIndexes = [];
        $this->envRepo = $environmentRepository;
        $this->logger = $logger;
        $this->managedAliasRepo = $managedAliasRepository;
        $this->elasticaClient = $elasticaClient;
        $this->elasticaService = $elasticaService;
    }

    /**
     * @return array<int, array{ add: array{alias: string, index: string}} | array{remove: array{alias: string, index: string}} >
     */
    public function atomicSwitch(Environment $environment, string $newIndex): array
    {
        $aliasName = $environment->getAlias();
        $actions = [];
        $actions[] = ['add' => ['alias' => $aliasName, 'index' => $newIndex]];

        if ($oldIndex = $this->getEnvironmentIndex($environment)) {
            $actions[] = ['remove' => ['alias' => $aliasName, 'index' => $oldIndex]];

            if ($environment->isUpdateReferrers()) {
                foreach ($this->getReferrers($oldIndex) as $referrerAlias) {
                    if ($referrerAlias['name'] === $environment->getAlias()) {
                        continue;
                    }

                    $actions[] = ['remove' => ['alias' => $referrerAlias['name'], 'index' => $oldIndex]];
                    $actions[] = ['add' => ['alias' => $referrerAlias['name'], 'index' => $newIndex]];
                }
            }
        }

        $endpoint = new Update();
        $endpoint->setBody(['actions' => $actions]);
        $this->elasticaClient->requestEndpoint($endpoint);

        return $actions;
    }

    public function hasAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    public function hasAliasInCluster(string $name): bool
    {
        $endpoint = new Get();
        $endpoint->setName($name);
        try {
            $this->elasticaClient->requestEndpoint($endpoint)->getData();

            return true;
        } catch (\Throwable $e) {
        }

        return false;
    }

    /**
     * @return array{name: string, total: int, indexes: array, environment: string, managed: bool}
     */
    public function getAlias(string $name): array
    {
        if (!$this->isBuild) {
            $this->build();
        }

        return $this->aliases[$name];
    }

    /**
     * @return array<string, array{name: string, total: int, indexes: array, environment: null|string, managed: bool}>
     */
    public function getAliases(): array
    {
        if (!$this->isBuild) {
            $this->build();
        }

        return $this->aliases;
    }

    public function getManagedAlias(int $id): ?ManagedAlias
    {
        if (!$this->isBuild) {
            $this->build();
        }

        /** @var ManagedAlias|null $managedAlias */
        $managedAlias = $this->managedAliasRepo->find($id);

        if (null !== $managedAlias && $this->hasAlias($managedAlias->getAlias())) {
            $alias = $this->getAlias($managedAlias->getAlias());
            $managedAlias->setIndexes($alias['indexes']);
        }

        return $managedAlias;
    }

    public function getManagedAliasByName(string $name): ManagedAlias
    {
        /** @var ManagedAlias|null $managedAlias */
        $managedAlias = $this->managedAliasRepo->findOneBy([
            'name' => $name,
        ]);

        if (null === $managedAlias) {
            throw new \RuntimeException('Unexpected null managed alias');
        }

        if ($this->hasAlias($managedAlias->getAlias())) {
            $alias = $this->getAlias($managedAlias->getAlias());
            $managedAlias->setIndexes($alias['indexes']);
        }

        return $managedAlias;
    }

    /**
     * @return ManagedAlias[]
     */
    public function getManagedAliases(): array
    {
        $managedAliases = $this->managedAliasRepo->findAll();

        foreach ($managedAliases as $managedAlias) {
            /* @var $managedAlias ManagedAlias */
            if (!$this->hasAlias($managedAlias->getAlias())) {
                continue;
            }

            $alias = $this->getAlias($managedAlias->getAlias());
            $managedAlias->setIndexes($alias['indexes']);
            $managedAlias->setTotal($alias['total']);
        }

        return $managedAliases;
    }

    /**
     * @return array<mixed>
     */
    public function getAllIndexes(): array
    {
        $indexes = [];
        $endpoint = new Indices();
        $endpoint->setParams([
            'format' => 'JSON',
        ]);
        $indices = $this->elasticaClient->requestEndpoint($endpoint)->getData();

        foreach ($indices as $data) {
            $name = $data['index'];

            if (!$this->validIndexName($name)) {
                continue;
            }

            $indexes[$name] = ['name' => $name, 'count' => $data['docs.count']];
        }

        \ksort($indexes);

        return $indexes;
    }

    private function getEnvironmentIndex(Environment $environment): ?string
    {
        if (!$this->hasAlias($environment->getAlias())) {
            return null;
        }

        $alias = $this->getAlias($environment->getAlias());

        return \array_keys($alias['indexes'])[0];
    }

    /**
     * @return array<string, array{name: string, total: int, indexes: array, environment: string, managed: bool}>
     */
    private function getReferrers(string $indexName): array
    {
        return \array_filter(
            $this->aliases,
            fn (array $alias) => \array_key_exists($indexName, $alias['indexes'])
        );
    }

    /**
     * @return array<mixed>
     */
    public function getUnreferencedAliases(): array
    {
        $aliases = $this->getAliases();

        return \array_filter($aliases, function (array $alias) {
            return null === $alias['environment'] && false === $alias['managed'];
        });
    }

    /**
     * @return array<mixed>
     */
    public function getOrphanIndexes(): array
    {
        return $this->orphanIndexes;
    }

    public function build(): self
    {
        if ($this->isBuild) {
            return $this;
        }

        $data = $this->getData();
        $environmentAliases = $this->envRepo->findAllAliases();
        $managedAliases = $this->managedAliasRepo->findAllAliases();

        $search = new Search(['*']);
        $terms = new Terms(self::COUNTER_AGGREGATION);
        $terms->setField('_index');
        $terms->setSize(2000);
        $search->addAggregation($terms);
        $search->setSize(0);

        $resultSet = $this->elasticaService->search($search);

        if ($resultSet->hasAggregations()) {
            $aggregation = $resultSet->getAggregation(self::COUNTER_AGGREGATION);
            if (0 !== ($aggregation['sum_other_doc_count'] ?? 0) || \count($aggregation['buckets'] ?? []) >= 2000) {
                $this->logger->warning('service.alias.too_many_indexes');
            }
        } else {
            $aggregation = [];
        }

        $this->counterIndexes = [];
        foreach ($aggregation['buckets'] ?? [] as $bucket) {
            $index = $bucket['key'] ?? '';
            if (\is_string($index) && $this->validIndexName($index)) {
                $this->counterIndexes[$bucket['key']] = $bucket['doc_count'];
            }
        }

        foreach ($data as $index => $info) {
            $aliases = \array_keys($info['aliases']);
            foreach ($aliases as $alias) {
                if (\is_string($alias) && !isset($this->counterIndexes[$alias])) {
                    $this->counterIndexes[$alias] = 0;
                }
                $this->counterIndexes[$alias] += $this->counterIndexes[$index] ?? 0;
            }
        }

        foreach ($data as $index => $info) {
            $aliases = \array_keys($info['aliases']);

            if (0 === \count($aliases)) {
                $this->addOrphanIndex($index);
                continue;
            }

            foreach ($aliases as $alias) {
                if (!\is_string($alias)) {
                    continue;
                }
                if (\array_key_exists($alias, $environmentAliases)) {
                    $this->addAlias($alias, $index, $environmentAliases[$alias]);
                } elseif (\in_array($alias, $managedAliases)) {
                    $this->addAlias($alias, $index, [], true);
                } else {
                    $this->addAlias($alias, $index);
                }
            }
        }
        $this->isBuild = true;

        return $this;
    }

    /**
     * @param array<mixed> $actions
     */
    public function updateAlias(string $alias, array $actions): void
    {
        if (empty($actions)) {
            return;
        }

        $json = [];
        foreach ($actions as $type => $indexes) {
            foreach ($indexes as $index) {
                $json[] = [$type => ['index' => $index, 'alias' => $alias]];
            }
        }

        $endpoint = new Update();
        $endpoint->setBody(['actions' => $json]);
        $this->elasticaClient->requestEndpoint($endpoint);
    }

    public function removeAlias(string $name): bool
    {
        $this->build();
        if (!$this->hasAlias($name)) {
            return false;
        }

        $indexesToRemove = [];
        $alias = $this->getAlias($name);

        foreach ($alias['indexes'] as $index) {
            $indexesToRemove[] = $index['name'];
        }

        $this->updateAlias($name, ['remove' => $indexesToRemove]);

        return true;
    }

    /**
     * @param array<mixed> $env
     */
    private function addAlias(string $name, string $index, array $env = [], bool $managed = false): void
    {
        if ($this->hasAlias($name)) {
            $this->aliases[$name]['indexes'][$index] = $this->getIndex($index);

            return;
        }

        $this->aliases[$name] = [
            'name' => $name,
            'indexes' => [$index => $this->getIndex($index)],
            'total' => $this->count($name),
            'environment' => isset($env['name']) ? $env['name'] : null,
            'managed' => isset($env['managed']) ? $env['managed'] : $managed,
        ];
    }

    private function addOrphanIndex(string $name): void
    {
        $this->orphanIndexes[] = $this->getIndex($name);
    }

    /**
     * @return array{name: string, count: int}
     */
    private function getIndex(string $name): array
    {
        return ['name' => $name, 'count' => $this->count($name)];
    }

    private function count(string $name): int
    {
        return $this->counterIndexes[$name] ?? 0;
    }

    /**
     * @return array<mixed>
     */
    private function getData(): array
    {
        $endpoint = new Get();
        $indexesAliases = $this->elasticaClient->requestEndpoint($endpoint)->getData();

        return \array_filter(
            $indexesAliases,
            [$this, 'validIndexName'],
            \ARRAY_FILTER_USE_KEY
        );
    }

    private function validIndexName(string $index): bool
    {
        return \strlen($index) > 0 && '.' !== \substr($index, 0, 1);
    }
}
