<?php


namespace EMS\CoreBundle\Service;

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

    public function __construct(LoggerInterface $logger, ContentTypeService $contentTypeService, AuthorizationCheckerInterface $authorizationChecker, TokenStorageInterface $tokenStorage, ElasticaService $elasticaService)
    {
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
        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TokenInterface) {
            throw new \RuntimeException('Unexpected security token object');
        }
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException('Unexpected user entity object');
        }

        $cts = \explode(',', $types);
        foreach ($cts as $type) {
            if (! ($this->fullyLoaded[$type] ?? false)) {
                $currentType = $this->contentTypeService->getByName($type);
                if ($currentType !== false) {
                    $index = $this->contentTypeService->getIndex($currentType);

                    if ($circleOnly && !$this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT')) {
                        $circles = $user->getCircles();
                        $ouuids = [];
                        foreach ($circles as $circle) {
                            preg_match('/(?P<type>(\w|-)+):(?P<ouuid>(\w|-)+)/', $circle, $matches);
                            $ouuids[] = $matches['ouuid'];
                        }
                        $search = $this->elasticaService->generateTermsSearch([$index], '_id', $ouuids);
                    } else {
                        $search = new Search([$index]);
                    }

                    $search->setContentTypes([$type]);

                    if ($currentType->getOrderField()) {
                        $search->setSort([
                            $currentType->getOrderField() => [
                                'order' => 'asc',
                                'missing' => "_last",
                            ]
                        ]);
                    }
                    if ($currentType->getLabelField()) {
                        $search->setSources([$currentType->getLabelField()]);
                    }

                    $scroll = $this->elasticaService->scroll($search);

                    foreach ($scroll as $resultSet) {
                        foreach ($resultSet as $result) {
                            if ($result === false) {
                                continue;
                            }
                            $hitDocument = Document::fromResult($result);
                            if (!isset($choices[$hitDocument->getEmsId()])) {
                                $itemContentType = $this->contentTypeService->getByName($hitDocument->getContentType());
                                $listItem = new ObjectChoiceListItem($hitDocument, $itemContentType ? $itemContentType : null);
                                $choices[$listItem->getValue()] = $listItem;
                                $this->cache[$hitDocument->getContentType()][$hitDocument->getId()] = $listItem;
                            }
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
                        $contentType = $this->contentTypeService->getByName($objectType);
                        if ($contentType) {
                            $index = $this->contentTypeService->getIndex($contentType);
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
            $boolQuery = $this->elasticaService->getBoolQuery();
            $sourceField = [];
            foreach ($missingOuuidsPerType as $type => $ouuids) {
                $ouuidsQuery = $this->elasticaService->filterByContentTypes($this->elasticaService->getTermsQuery('_id', $ouuids), [$type]);
                if ($ouuidsQuery !== null) {
                    $boolQuery->addShould($ouuidsQuery);
                }

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
                    $document = Document::fromResult($result);
                    $contentType = $this->contentTypeService->getByName($document->getContentType());
                    if ($contentType === false) {
                        continue;
                    }
                    $listItem = new ObjectChoiceListItem($document, $contentType);
                    $this->cache[$document->getContentType()][$document->getId()] = $listItem;
                    $choices[$document->getEmsId()] = $listItem;
                }
            }
        }
        return $choices;
    }
}
