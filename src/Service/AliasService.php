<?php

namespace EMS\CoreBundle\Service;

use Elastica\Client as ElasticaClient;
use Elasticsearch\Endpoints\Cat\Indices;
use Elasticsearch\Endpoints\Indices\Alias\Get;
use Elasticsearch\Endpoints\Indices\Aliases\Update;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\ManagedAliasRepository;

class AliasService
{
    /** @var EnvironmentRepository */
    private $envRepo;
    /** @var ManagedAliasRepository */
    private $managedAliasRepo;
    /** @var array<string, array{total: int, indexes: array, environment: string, managed: bool}> */
    private $aliases = [];
    /** @var array<array{name: string, count: int}> */
    private $orphanIndexes = [];
    /** @var bool */
    private $isBuild = false;
    /** @var ElasticaClient */
    private $elasticaClient;
    /** @var ElasticaService */
    private $elasticaService;

    public function __construct(ElasticaClient $elasticaClient, EnvironmentRepository $environmentRepository, ManagedAliasRepository $managedAliasRepository, ElasticaService $elasticaService)
    {
        $this->envRepo = $environmentRepository;
        $this->managedAliasRepo = $managedAliasRepository;
        $this->elasticaClient = $elasticaClient;
        $this->elasticaService = $elasticaService;
    }

    public function hasAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    /**
     * @return array{total: int, indexes: array, environment: string, managed: bool}
     */
    public function getAlias(string $name)
    {
        return $this->aliases[$name];
    }

    /**
     * @return array<string, array{total: int, indexes: array, environment: string, managed: bool}>
     */
    public function getAliases()
    {
        return $this->aliases;
    }

    /**
     * @param int $id
     *
     * @return ManagedAlias|null
     */
    public function getManagedAlias($id)
    {
        /** @var ManagedAlias|null $managedAlias */
        $managedAlias = $this->managedAliasRepo->find($id);

        if ($this->hasAlias($managedAlias->getAlias())) {
            $alias = $this->getAlias($managedAlias->getAlias());
            $managedAlias->setIndexes($alias['indexes']);
        }

        return $managedAlias;
    }

    public function getManagedAliasByName(string $name)
    {
        /** @var ManagedAlias|null $managedAlias */
        $managedAlias = $this->managedAliasRepo->findOneBy([
            'name' => $name,
        ]);

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
     * @return array
     */
    public function getAllIndexes()
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

    /**
     * Aliases without an environment.
     *
     * @return array
     */
    public function getUnreferencedAliases()
    {
        $aliases = $this->getAliases();

        return \array_filter($aliases, function (array $alias) {
            return null === $alias['environment'] && false === $alias['managed'];
        });
    }

    /**
     * Indexes without aliases.
     *
     * @return array
     */
    public function getOrphanIndexes()
    {
        return $this->orphanIndexes;
    }

    /**
     * Build orphan indexes, unreferenced aliases.
     *
     * @return self
     */
    public function build()
    {
        if ($this->isBuild) {
            return $this;
        }

        $data = $this->getData();
        $environmentAliases = $this->envRepo->findAllAliases();
        $managedAliases = $this->managedAliasRepo->findAllAliases();

        foreach ($data as $index => $info) {
            $aliases = \array_keys($info['aliases']);

            if (0 === \count($aliases)) {
                $this->addOrphanIndex($index);
                continue;
            }

            foreach ($aliases as $alias) {
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

    /**
     * @param string $name
     */
    public function removeAlias($name): bool
    {
        if (!$this->hasAlias($name)) {
            return false;
        }

        $indexesToRemove = [];
        $alias = $this->getAlias($name);

        foreach ($alias['indexes'] as $index) {
            $indexesToRemove[] = $index['name'];
        }

        $this->updateAlias($name, ['actions' => ['remove' => $indexesToRemove]]);

        return true;
    }

    /**
     * @param string $name
     * @param string $index
     * @param bool   $managed
     *
     * @return void
     */
    private function addAlias($name, $index, array $env = [], $managed = false)
    {
        if ($this->hasAlias($name)) {
            $this->aliases[$name]['indexes'][$index] = $this->getIndex($index);

            return;
        }

        $this->aliases[$name] = [
            'indexes' => [$index => $this->getIndex($index)],
            'total' => $this->count($name),
            'environment' => isset($env['name']) ? $env['name'] : null,
            'managed' => isset($env['managed']) ? $env['managed'] : $managed,
        ];
    }

    /**
     * @param string $name
     */
    private function addOrphanIndex($name)
    {
        $this->orphanIndexes[] = $this->getIndex($name);
    }

    private function getIndex($name)
    {
        return ['name' => $name, 'count' => $this->count($name)];
    }

    /**
     * @param string $name
     *
     * @return int
     */
    private function count($name)
    {
        $search = new Search([$name]);
        return $this->elasticaService->count($search);
    }

    /**
     * Filters out indexes that start with .*.
     *
     * @return array
     */
    private function getData()
    {
        $endpoint = new Get();
        $indexesAliases = $this->elasticaClient->requestEndpoint($endpoint)->getData();

        return \array_filter(
            $indexesAliases,
            [$this, 'validIndexName'],
            \ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    private function validIndexName($name)
    {
        return 0 != \strcmp($name[0], '.');
    }
}
