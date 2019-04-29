<?php


namespace EMS\CoreBundle\Service;

use Elasticsearch\Client;
use EMS\LocalUserBundle\Entity\User;
use Symfony\Component\HttpFoundation\Session\Session;
use EMS\CoreBundle\Form\Field\ObjectChoiceListItem;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ObjectChoiceCacheService
{
    /**@Client $client*/
    private $client;
    /**@var Session $session*/
    private $session;
    /**@var ContentTypeService $contentTypeService*/
    private $contentTypeService;
    /**@var AuthorizationCheckerInterface $authorizationChecker*/
    protected $authorizationChecker;
    /**@var TokenStorageInterface $tokenStorage*/
    protected $tokenStorage;
    
    private $fullyLoaded;
    private $cache;
    
    public function __construct(Client $client, Session $session, ContentTypeService $contentTypeService, AuthorizationCheckerInterface $authorizationChecker, TokenStorageInterface $tokenStorage)
    {
        $this->client = $client;
        $this->session = $session;
        $this->contentTypeService = $contentTypeService;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;

        $this->fullyLoaded = [];
        $this->cache = [];
    }
    

    public function loadAll(array &$choices, $types, $circleOnly = false)
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
                            'size'=>  '500',
                            'index'=> $currentType->getEnvironment()->getAlias(),
                            'type'=> $type,
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
                        /**@var User $user */
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
                    //TODO test si > 500...flashbag

                    foreach ($items['hits']['hits'] as $hit) {
                        if (!isset($choices[$hit['_type'].':'.$hit['_id']])) {
                            $listItem = new ObjectChoiceListItem($hit, $this->contentTypeService->getByName($hit['_type']));
                            $choices[$listItem->getValue()] = $listItem;
                            $this->cache[$hit['_type']][$hit['_id']] = $listItem;
                        }
                    }
                } else {
                    $this->session->getFlashBag()->add('warning', 'ems was not able to find the content type "'.$type.'"');
                }
                $this->fullyLoaded[$type] = true;
            } else {
                foreach ($this->cache[$type] as $id => $item) {
                    if ($item && !isset($choices[$type.':'.$id])) {
                        $choices[$type.':'.$id] = $item;
                    }
                }
            }
        }
    }
    
    public function load($objectIds, $circleOnly = false)
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
                                    "_type" => $ref[0],
                                    "_id" => $ref[1],
                                ];
                            } else {
                                $this->session->getFlashBag()->add('warning', 'ems was not able to find the alias for the content type "'.$ref[0].'"');
                            }
                        } else {
                            $this->session->getFlashBag()->add('warning', 'ems was not able to find the content type "'.$ref[0].'"');
                        }
                    }
                }
            } else {
                if (null !== $objectId && $objectId !== "") {
                    $this->session->getFlashBag()->add('warning', 'ems was not able to parse the object key "'.$objectId.'"');
                }
            }
        }
        
        foreach ($queries as $alias => $query) {
            $params = [
                    'index' => $alias,
                    'body' => $query
            ];
            $result = $this->client->mget($params);
            foreach ($result['docs'] as $doc) {
                $objectId = $doc['_type'].':'.$doc['_id'];
                if ($doc['found']) {
                    $listItem = new ObjectChoiceListItem($doc, $this->contentTypeService->getByName($doc['_type']));
                    $this->cache[$doc['_type']][$doc['_id']] = $listItem;
                    $out[$objectId] = $listItem;
                } else {
                    $this->cache[$doc['_type']][$doc['_id']] = false;
                    $this->session->getFlashBag()->add('warning', 'ems was not able to find the object key "'.$objectId.'"');
                }
            }
        }
        return $out;
    }
}
