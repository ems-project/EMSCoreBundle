<?php


namespace EMS\CoreBundle\Service;

use Elastica\Query\BoolQuery;
use Elastica\Query\Terms;
use Elasticsearch\Client;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Field\ObjectChoiceListItem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ObjectChoiceCacheService
{
    /** @Client $client*/
    private $client;
    /** @var LoggerInterface $logger*/
    private $logger;
    /** @var ContentTypeService $contentTypeService*/
    private $contentTypeService;
    /** @var AuthorizationCheckerInterface $authorizationChecker*/
    protected $authorizationChecker;
    /** @var TokenStorageInterface $tokenStorage*/
    protected $tokenStorage;
    /** @var ElasticaService */
    private $elasticaService;
    /** @var bool[] */
    private $fullyLoaded;
    /** @var array<ObjectChoiceListItem[]> */
    private $cache;

    public function __construct(Client $client, LoggerInterface $logger, ContentTypeService $contentTypeService, AuthorizationCheckerInterface $authorizationChecker, TokenStorageInterface $tokenStorage, ElasticaService $elasticaService)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->contentTypeService = $contentTypeService;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->elasticaService = $elasticaService;

        $this->fullyLoaded = [];
        $this->cache = [];
    }

    /**
     * @param ObjectChoiceListItem[] $choices
     */
    public function loadAll(array &$choices, string $types, bool $circleOnly = false, bool $withWarning = true): void
    {
        $aliasTypes = [];
        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TokenInterface) {
            throw new \RuntimeException('Unexpected security token object');
        }
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException('Unexpected user entity object');
        }

        $cts = explode(',', $types);
        foreach ($cts as $type) {
            if (!isset($this->fullyLoaded[$type])) {
                $currentType = $this->contentTypeService->getByName($type);
                if ($currentType !== false) {
                    $currentTypeDefaultEnvironment = $currentType->getEnvironment();
                    if ($currentTypeDefaultEnvironment === null) {
                        throw new \RuntimeException(sprintf('Unexpected null environment for content type %s', $type));
                    }
                    if (!isset($aliasTypes[$currentTypeDefaultEnvironment->getAlias()])) {
                        $aliasTypes[$currentTypeDefaultEnvironment->getAlias()] = [];
                    }
                    $aliasTypes[$currentTypeDefaultEnvironment->getAlias()][] = $type;
                    $params = [
                            'size' =>  '500',
                            'index' => $currentTypeDefaultEnvironment->getAlias(),
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
                        $hitDocument = new Document($hit);
                        if (!isset($choices[$hitDocument->getEmsId()])) {
                            $itemContentType = $this->contentTypeService->getByName($hitDocument->getContentType());
                            $listItem = new ObjectChoiceListItem($hit, $itemContentType ? $itemContentType : null);
                            $choices[$listItem->getValue()] = $listItem;
                            $this->cache[$hitDocument->getContentType()][$hitDocument->getId()] = $listItem;
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
                    if (!isset($choices[$type . ':' . $id])) {
                        $choices[$type . ':' . $id] = $item;
                    }
                }
            }
        }
    }

    /**
     * @param string[] $objectIds
     * @return ObjectChoiceListItem[]
     */
    public function load(array $objectIds, bool $circleOnly = false, bool $withWarning = true): array
    {
        $choices = [];
        $missingOuuidsPerIndexAndType = [];
        foreach ($objectIds as $objectId) {
            if (\is_string($objectId) && \strpos($objectId, ':') !== false) {
                list($objectType, $objectOuuid) = \explode(':', $objectId);
                if (!isset($this->cache[$objectType])) {
                    $this->cache[$objectType] = [];
                }

                if (isset($this->cache[$objectType][$objectOuuid])) {
                    $choices[$objectId] = $this->cache[$objectType][$objectOuuid];
                } else {
                    if (!isset($this->fullyLoaded[$objectType])) {
                        $contentTypeName = $this->contentTypeService->getByName($objectType);
                        if ($contentTypeName) {
                            $index = $this->contentTypeService->getIndex($contentTypeName);
                            if ($index) {
                                $missingOuuidsPerIndexAndType[$index][$objectType][] = $objectOuuid;
                            } elseif ($withWarning) {
                                $this->logger->warning('service.object_choice_cache.alias_not_found', [
                                    EmsFields::LOG_CONTENTTYPE_FIELD => $objectType,
                                ]);
                            }
                        } elseif ($withWarning) {
                            $this->logger->warning('service.object_choice_cache.contenttype_not_found', [
                                EmsFields::LOG_CONTENTTYPE_FIELD => $objectType,
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

        foreach ($missingOuuidsPerIndexAndType as $indexName => $missingOuuidsPerType) {
            $boolQuery = new BoolQuery();
            $sourceField = [];
            foreach ($missingOuuidsPerType as $type => $ouuids) {
                $ouuidsQuery = new Terms('_id', $ouuids);
                $boolQuery->addShould($this->elasticaService->filterByContentTypes($ouuidsQuery, [$type]));

                $contentType = $this->contentTypeService->getByName($type);
                if ($contentType !== false && !empty($contentType->getLabelField()) && !\in_array($contentType->getLabelField(), $sourceField)) {
                    $sourceField[] = $contentType->getLabelField();
                }
            }
            $boolQuery->setMinimumShouldMatch(1);

            $search = new Search([$indexName], $boolQuery);
            $search->setSources($sourceField);


            $scroll = $this->elasticaService->scroll($search);
            foreach ($scroll as $resultSet) {
                foreach ($resultSet as $result) {
                    if ($result === false) {
                        continue;
                    }
                    $hit = $result->getHit();

                    $document = new Document($hit);
                    $contentType = $this->contentTypeService->getByName($document->getContentType());
                    if ($contentType === false) {
                        continue;
                    }
                    $listItem = new ObjectChoiceListItem($hit, $contentType);
                    $this->cache[$document->getContentType()][$document->getId()] = $listItem;
                    $choices[$document->getEmsId()] = $listItem;
                }
            }
        }
        return $choices;
    }
}
