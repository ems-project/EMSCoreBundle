<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\ManagedAliasRepository;

class AliasService
{
    /**
     * @var Client
     */
    private $client;
    
    /**
     * @var EnvironmentRepository
     */
    private $envRepo;
    
    /**
     * @var ManagedAliasRepository
     */
    private $managedAliasRepo;
    
    /**
     * [name => [indexes, total, environment, managed]]
     *
     * @var array
     */
    private $aliases = [];

    /**
     * [name => [[name => count]]
     *
     * @var array
     */
    private $orphanIndexes = [];
    
    /**
     * @var bool
     */
    private $isBuild = false;

    /**
     * @param Client   $client
     * @param Registry $registry
     */
    public function __construct(Client $client, Registry $doctrine)
    {
        $this->client = $client;
        $this->envRepo = $doctrine->getRepository(Environment::class);
        $this->managedAliasRepo = $doctrine->getRepository(ManagedAlias::class);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasAlias($name)
    {
        return isset($this->aliases[$name]);
    }

    /**
     * @param string $name
     *
     * @return array
     */
    public function getAlias($name)
    {
        return $this->aliases[$name];
    }
    
    /**
     * Get all aliases
     *
     * @return array
     */
    public function getAliases()
    {
        return $this->aliases;
    }
    
    /**
     * @param int $id
     *
     * @return ManagedAlias
     */
    public function getManagedAlias($id)
    {
        $managedAlias = $this->managedAliasRepo->find($id);
        
        if ($this->hasAlias($managedAlias->getAlias())) {
            $alias = $this->getAlias($managedAlias->getAlias());
            $managedAlias->setIndexes($alias['indexes']);
        }
        
        return $managedAlias;
    }
    
    /**
     * @return ManagedAliases[]
     */
    public function getManagedAliases()
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
        $indices = $this->client->cat()->indices();
        
        foreach ($indices as $data) {
            $name = $data['index'];
            
            if (!$this->validIndexName($name)) {
                continue;
            }
            
            $indexes[$name] = ['name' => $name, 'count' => $data['docs.count']];
        }
        
        ksort($indexes);
        
        return $indexes;
    }
    
    /**
     * Aliases without an environment
     *
     * @return array
     */
    public function getUnreferencedAliases()
    {
        $aliases = $this->getAliases();
        
        return array_filter($aliases, function (array $alias) {
            return $alias['environment'] === null && $alias['managed'] === false;
        });
    }

    /**
     * Indexes without aliases
     *
     * @return array
     */
    public function getOrphanIndexes()
    {
        return $this->orphanIndexes;
    }

    /**
     * Build orphan indexes, unreferenced aliases
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
            $aliases = array_keys($info['aliases']);
            
            if (0 === count($aliases)) {
                $this->addOrphanIndex($index);
                continue;
            }
            
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $environmentAliases)) {
                    $this->addAlias($alias, $index, $environmentAliases[$alias]);
                } else if (in_array($alias, $managedAliases)) {
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
     * @param string $alias
     * @param array  $actions
     *
     * @return void
     */
    public function updateAlias($alias, array $actions)
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
        
        $this->client->indices()->updateAliases([
            'body' => ['actions' => $json]
        ]);
    }
    
    /**
     * @param string $alias
     */
    public function removeAlias($name)
    {
        if (!$this->hasAlias($name)) {
            return false;
        }
        
        $actions = [];
        $alias = $this->getAlias($name);
        
        foreach ($alias['indexes'] as $index) {
            $actions[] = ['remove' => ['index' => $index['name'], 'alias' => $name]];
        }
        
        $this->client->indices()->updateAliases([
            'body' => ['actions' => $actions]
        ]);
        
        return true;
    }
        
    /**
     * @param string $name
     * @param string $index
     * @param array  $env
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
        $result = $this->client->count(['index' => $name]);
        
        return isset($result['count']) ? (int) $result['count'] : 0;
    }

    /**
     * Filters out indexes that start with .*
     *
     * @return array
     */
    private function getData()
    {
        $indexesAliases = $this->client->indices()->getAliases();

        return array_filter(
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
        return strcmp($name{0}, '.') != 0;
    }
}
