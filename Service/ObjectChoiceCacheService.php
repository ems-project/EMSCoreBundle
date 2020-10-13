<?php


namespace EMS\CoreBundle\Service;

use Elasticsearch\Client;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Field\ObjectChoiceListItem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ObjectChoiceCacheService
{
    /**@Client $client*/
    private $client;
    /**@var LoggerInterface $logger*/
    private $logger;
    /**@var ContentTypeService $contentTypeService*/
    private $contentTypeService;
    /**@var AuthorizationCheckerInterface $authorizationChecker*/
    protected $authorizationChecker;
    /**@var TokenStorageInterface $tokenStorage*/
    protected $tokenStorage;
    
    private $fullyLoaded;
    private $cache;
    
    public function __construct(Client $client, LoggerInterface $logger, ContentTypeService $contentTypeService, AuthorizationCheckerInterface $authorizationChecker, TokenStorageInterface $tokenStorage)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->contentTypeService = $contentTypeService;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;

        $this->fullyLoaded = [];
        $this->cache = [];
    }
    

    public function loadAll(array &$choices, $types, bool $circleOnly = false, bool $withWarning = true)
    {
        $aliasTypes = [];
        
        $cts = explode(',', $types);
        foreach ($cts as $type) {
            if (!isset($this->fullyLoaded[$type])) {
                $currentType = $this->contentTypeService->getByName($type);
                if ($currentType) {
                    if (!isset($aliasTypes[$currentType->getEnvironment()->getAlias()])) {
                        $aliasTypes[$currentType->getEnvironment()->getAlias()] = [];
                    }
                    $aliasTypes[$currentType->getEnvironment()->getAlias()][] = $type;
                    $params = [
                            'size' =>  '500',
                            'index' => $currentType->getEnvironment()->getAlias(),
                            'type' => $type,
                    ];


                    if ($currentType->getOrderField()) {
                        $params['body'] = [
                            'sort' => [
                                $currentType->getOrderField() => [
                                    'order' => 'asc',
                                    'missing' => "_last",
                                ]
                            ]
                        ];
                    }

                    if ($circleOnly && !$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                        /** @var UserInterface $user */
                        $user = $this->tokenStorage->getToken()->getUser();
                        $circles = $user->getCircles();
                        $ouuids = [];
                        foreach ($circles as $circle) {
                            preg_match('/(?P<type>(\w|-)+):(?P<ouuid>(\w|-)+)/', $circle, $matches);
                            $ouuids[] = $matches['ouuid'];
                        }

                        $params['body']['query']['terms'] = [
                                '_id' => $ouuids,
                        ];
                    }

                    $items = $this->client->search($params);
                    //TODO test si > 500... logger

                    foreach ($items['hits']['hits'] as $hit) {
                        if (!isset($choices[$hit['_type'] . ':' . $hit['_id']])) {
                            $listItem = new ObjectChoiceListItem($hit, $this->contentTypeService->getByName($hit['_type']));
                            $choices[$listItem->getValue()] = $listItem;
                            $this->cache[$hit['_type']][$hit['_id']] = $listItem;
                        }
                    }
                } elseif ($withWarning) {
                    $this->logger->warning('service.object_choice_cache.contenttype_not_found', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $type,
                    ]);
                }
                $this->fullyLoaded[$type] = true;
            } else {
                foreach ($this->cache[$type] as $id => $item) {
                    if ($item && !isset($choices[$type . ':' . $id])) {
                        $choices[$type . ':' . $id] = $item;
                    }
                }
            }
        }
    }
    
    public function load($objectIds, bool $circleOnly = false, bool $withWarning = true)
    {
        $out = [];
        $queries = [];
        foreach ($objectIds as $objectId) {
            if (is_string($objectId) && strpos($objectId, ':') !== false) {
                $ref = explode(':', $objectId);
                if (!isset($this->cache[$ref[0]])) {
                    $this->cache[$ref[0]] = [];
                }
                
                if (isset($this->cache[$ref[0]][$ref[1]])) {
                    if ($this->cache[$ref[0]][$ref[1]]) {
                        $out[$objectId] = $this->cache[$ref[0]][$ref[1]];
                    }
                } else {
                    if (!isset($this->fullyLoaded[$ref[0]])) {
                        $contentType = $this->contentTypeService->getByName($ref[0]);
                        if ($contentType) {
                            $index = $this->contentTypeService->getIndex($contentType);
                            if ($index) {
                                if (!array_key_exists($index, $queries)) {
                                    $queries[$index] = ['docs' => []];
                                }
                                $queries[$index]['docs'][] = [
                                    '_type' => $ref[0],
                                    '_id' => $ref[1],
                                    'body' => [
                                        'query' => [
                                            'bool' => [
                                                'must' => [
                                                    [ 'term' => ['_contenttype' => $ref[0]]],
                                                    [ 'term' => ['_id' => $ref[1]]]
                                                ]
                                            ]
                                        ]
                                    ]
                                ];
                            } elseif ($withWarning) {
                                $this->logger->warning('service.object_choice_cache.alias_not_found', [
                                    EmsFields::LOG_CONTENTTYPE_FIELD => $ref[0],
                                ]);
                            }
                        } elseif ($withWarning) {
                            $this->logger->warning('service.object_choice_cache.contenttype_not_found', [
                                EmsFields::LOG_CONTENTTYPE_FIELD => $ref[0],
                            ]);
                        }
                    }
                }
            } else {
                if (null !== $objectId && $objectId !== "" && $withWarning) {
                    $this->logger->warning('service.object_choice_cache.object_key_not_found', [
                        'object_key' => $objectId,
                    ]);
                }
            }
        }

        foreach ($queries as $alias => $query) {
            foreach ($query['docs'] as $docItem) {
                $params = [
                    'index' => $alias,
                    'body' => $docItem['body']
                ];
                $objectId = $docItem['_type'] . ':' . $docItem['_id'];
                $result = $this->client->search($params);
                if ($result['hits']['total'] === 1) {
                    $doc = $result['hits']['hits'][0];
                    $listItem = new ObjectChoiceListItem($doc, $this->contentTypeService->getByName($doc['_type']));
                    $this->cache[$doc['_type']][$doc['_id']] = $listItem;
                    $out[$objectId] = $listItem;
                } else {
                    $this->cache[$docItem['_type']][$docItem['_id']] = false;
                    if ($withWarning) {
                        $this->logger->warning('service.object_choice_cache.object_key_not_found', [
                            'object_key' => $objectId,
                        ]);
                    }
                }
            }
        }
        return $out;
    }
}
