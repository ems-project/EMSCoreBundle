<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\ClientHelperBundle\Helper\Cache\CacheHelper;
use EMS\ClientHelperBundle\Helper\Elasticsearch\ClientRequest;
use EMS\ClientHelperBundle\Helper\Environment\Environment;
use EMS\ClientHelperBundle\Helper\Environment\SingleEnvironmentHelper;
use EMS\ClientHelperBundle\Helper\Routing\BaseRouter;
use EMS\ClientHelperBundle\Helper\Routing\Route;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Repository\ChannelRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Routing\RouteCollection;

final class ChannelRegisterService extends BaseRouter
{
    private ChannelRepository $channelRepository;
    private ElasticaService $elasticaService;
    private LoggerInterface $logger;
    private CacheHelper $cache;
    private AdapterInterface $adapter;

    public function __construct(
        ChannelRepository $channelRepository,
        ElasticaService $elasticaService,
        LoggerInterface $logger,
        CacheHelper $cache,
        AdapterInterface $adapter
    ) {
        $this->channelRepository = $channelRepository;
        $this->elasticaService = $elasticaService;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->adapter = $adapter;
    }

    public function getRouteCollection()
    {
        if (null === $this->collection) {
            $this->buildRouteCollection();
        }

        return $this->collection;
    }

    public function buildRouteCollection(): void
    {
        $collection = new RouteCollection();
        $this->addEMSRoutes($collection);
        $this->collection = $collection;
    }

    private function addEMSRoutes(RouteCollection $collection): void
    {
        foreach ($this->channelRepository->getAll() as $channel) {
            $locale = $channel->getOptions()['locales'][0] ?? null;
            if (!\is_string($locale)) {
                throw new \RuntimeException('Unexpected locale type');
            }
            $name = $channel->getName();
            if (!\is_string($name)) {
                throw new \RuntimeException('Unexpected name type');
            }
            $slug = $channel->getSlug();
            if (!\is_string($slug)) {
                throw new \RuntimeException('Unexpected slug type');
            }

            $name = $channel->getOptions()['environment'];

            $options = [];

            $environment = new Environment($name, $options);
            $environmentHelper = new SingleEnvironmentHelper($name, $environment, $locale);
            $clientRequest = new ClientRequest($this->elasticaService, $environmentHelper, $this->logger, $this->adapter, $slug, [
                ClientRequest::OPTION_INDEX_PREFIX => \implode(',', $channel->getOptions()['instanceId']),
            ]);

            $routes = $this->getRoutes($slug, $clientRequest);

            foreach ($routes as $route) {
                /* @var $route Route */
                $route->addToCollection($collection, $channel->getOptions()['locales']);
            }
        }
    }

    /**
     * @return Route[]
     */
    private function getRoutes(string $channelSlug, ClientRequest $clientRequest): array
    {
        $cacheItem = $this->cache->get($clientRequest->getCacheKey('routes'));
        if (!$cacheItem instanceof CacheItem) {
            throw new \RuntimeException('Unexpected cache item type');
        }

        $type = 'route';
        $lastChanged = $clientRequest->getLastChangeDate($type);

        if ($this->cache->isValid($cacheItem, $lastChanged)) {
            return $this->cache->getData($cacheItem);
        }

        $routes = $this->createRoutes($channelSlug, $clientRequest, $type);
        $this->cache->save($cacheItem, $routes);

        return $routes;
    }

    /**
     * @return Route[]
     */
    private function createRoutes(string $channelSlug, ClientRequest $clientRequest, string $type): array
    {
        $routes = [];
        $scroll = $clientRequest->scrollAll([
            'size' => 100,
            'type' => $type,
            'sort' => ['order'],
        ], '5s');

        foreach ($scroll as $hit) {
            $source = $hit['_source'];
            $name = \sprintf('emsco.channel.%s.%s', $channelSlug, $source['name']);

            try {
                $options = \json_decode($source['config'], true);

                if (JSON_ERROR_NONE !== \json_last_error()) {
                    throw new \InvalidArgumentException(\sprintf('invalid json %s', $source['config']));
                }

                $options['query'] = $source['query'] ?? null;

                $staticTemplate = isset($source['template_static']) ? '@EMSCH/'.$source['template_static'] : null;
                $options['template'] = $source['template_source'] ?? $staticTemplate;
                $options['index_regex'] = $source['index_regex'] ?? null;

                $path = $options['path'] ?? null;
                if (\is_array($path)) {
                    foreach ($path as $locale => $subpath) {
                        $path[$locale] = \sprintf('channel/%s/%s', $channelSlug, $subpath);
                    }
                } elseif (\is_string($path)) {
                    $path = \sprintf('channel/%s/%s', $channelSlug, $path);
                } else {
                    throw new \RuntimeException('Unexpected path type');
                }

                $options['path'] = $path;

                $routes[] = new Route($name, $options);
            } catch (\Exception $e) {
                $this->logger->error('Router failed to create ems route {name} : {error}', ['name' => $name, 'error' => $e->getMessage()]);
            }
        }

        return $routes;
    }
}
