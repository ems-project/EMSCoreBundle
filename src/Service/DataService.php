<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\QueryBuilder;
use EMS\CommonBundle\Common\Document;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CommonBundle\Helper\ArrayTool;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Notification;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Event\RevisionFinalizeDraftEvent;
use EMS\CoreBundle\Event\RevisionNewDraftEvent;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Exception\DataStateException;
use EMS\CoreBundle\Exception\DuplicateOuuidException;
use EMS\CoreBundle\Exception\HasNotCircleException;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\PrivilegeException;
use EMS\CoreBundle\Form\DataField\CollectionFieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\DataLinkFieldType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\Revision\PostProcessingService;
use EMS\CoreBundle\Twig\AppExtension;
use Exception;
use IteratorAggregate;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;
use Twig_Environment;

/**
 * @todo Move Revision related logic to RevisionService
 */
class DataService
{
    public const ALGO = OPENSSL_ALGO_SHA1;
    protected const SCROLL_TIMEOUT = '1m';

    /** @var resource|false|null */
    private $private_key;
    /** @var string|null */
    private $public_key;
    /** @var string */
    protected $lockTime;
    /** @var string */
    protected $instanceId;

    /** @var array */
    private $cacheBusinessKey = [];
    /** @var array */
    private $cacheOuuids = [];

    /** @var Twig_Environment */
    protected $twig;
    /** @var Registry */
    protected $doctrine;
    /** @var AuthorizationCheckerInterface */
    protected $authorizationChecker;
    /** @var TokenStorageInterface */
    protected $tokenStorage;
    /** @var ElasticaService */
    protected $elasticaService;
    /** @var Mapping */
    protected $mapping;
    /** @var ObjectManager */
    protected $em;
    /** @var RevisionRepository */
    protected $revRepository;
    /** @var Session */
    protected $session;
    /** @var FormFactoryInterface */
    protected $formFactory;
    /** @var Container */
    protected $container;
    /** @var AppExtension */
    protected $appTwig;
    /** @var FormRegistryInterface */
    protected $formRegistry;
    /** @var EventDispatcher */
    protected $dispatcher;
    /** @var ContentTypeService */
    protected $contentTypeService;
    /** @var UserService */
    protected $userService;

    protected LoggerInterface $logger;
    private LoggerInterface $auditLogger;

    /** @var StorageManager */
    private $storageManager;
    /** @var EnvironmentService */
    private $environmentService;
    /** @var SearchService */
    private $searchService;
    /** @var IndexService */
    private $indexService;
    /** @var bool */
    private $preGeneratedOuuids;

    private PostProcessingService $postProcessingService;

    public function __construct(
        Registry $doctrine,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        string $lockTime,
        ElasticaService $elasticaService,
        Mapping $mapping,
        string $instanceId,
        Session $session,
        FormFactoryInterface $formFactory,
        Container $container,
        FormRegistryInterface $formRegistry,
        $dispatcher,
        ContentTypeService $contentTypeService,
        string $privateKey,
        LoggerInterface $logger,
        LoggerInterface $auditLogger,
        StorageManager $storageManager,
        Twig_Environment $twig,
        AppExtension $appExtension,
        UserService $userService,
        RevisionRepository $revisionRepository,
        EnvironmentService $environmentService,
        SearchService $searchService,
        IndexService $indexService,
        bool $preGeneratedOuuids,
        PostProcessingService $postProcessingService
    ) {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->auditLogger = $auditLogger;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->lockTime = $lockTime;
        $this->elasticaService = $elasticaService;
        $this->mapping = $mapping;
        $this->instanceId = $instanceId;
        $this->em = $this->doctrine->getManager();
        $this->revRepository = $revisionRepository;
        $this->session = $session;
        $this->formFactory = $formFactory;
        $this->container = $container;
        $this->twig = $twig;
        $this->appTwig = $appExtension;
        $this->formRegistry = $formRegistry;
        $this->dispatcher = $dispatcher;
        $this->storageManager = $storageManager;
        $this->contentTypeService = $contentTypeService;
        $this->userService = $userService;
        $this->environmentService = $environmentService;
        $this->searchService = $searchService;
        $this->indexService = $indexService;
        $this->preGeneratedOuuids = $preGeneratedOuuids;
        $this->postProcessingService = $postProcessingService;

        $this->public_key = null;
        $this->private_key = null;

        if (!empty($privateKey)) {
            try {
                $this->private_key = \openssl_pkey_get_private(\file_get_contents($privateKey));
            } catch (Exception $e) {
                $this->logger->warning('service.data.not_able_to_load_the_private_key', [
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    'private_key_filename' => $privateKey,
                ]);
            }
        }
    }

    public function unlockRevision(Revision $revision, $lockerUsername = null)
    {
        if (empty($lockerUsername)) {
            $lockerUsername = $this->tokenStorage->getToken()->getUsername();
        }
        if ($revision->getLockBy() === $lockerUsername && $revision->getLockUntil() > (new \DateTime())) {
            $this->revRepository->unlockRevision($revision->getId());
        }
    }

    /**
     * @param Environment $publishEnv
     * @param bool        $super
     * @param string|null $username
     *
     * @throws LockedException
     * @throws PrivilegeException
     * @throws Exception
     */
    public function lockRevision(Revision $revision, Environment $publishEnv = null, $super = false, $username = null): string
    {
        if (!empty($publishEnv) && !$this->authorizationChecker->isGranted($revision->giveContentType()->getPublishRole() ?: 'ROLE_PUBLISHER')) {
            throw new PrivilegeException($revision, 'You don\'t have publisher role for this content');
        }
        if (!empty($publishEnv) && \is_object($publishEnv) && !empty($publishEnv->getCircles()) && !$this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT') && !$this->appTwig->inMyCircles($publishEnv->getCircles())) {
            throw new PrivilegeException($revision, 'You don\'t share any circle with this content');
        }
        if (null === $username && empty($publishEnv) && !empty($revision->giveContentType()->getCirclesField()) && !empty($revision->getRawData()[$revision->giveContentType()->getCirclesField()])) {
            if (!$this->appTwig->inMyCircles($revision->getRawData()[$revision->giveContentType()->getCirclesField()] ?? [])) {
                throw new PrivilegeException($revision);
            }
        }

        /** @var Notification $notification */
        foreach ($revision->getNotifications() as $notification) {
            if (Notification::PENDING === $notification->getStatus() && !$this->authorizationChecker->isGranted($notification->getTemplate()->getRole())) {
                throw new PrivilegeException($revision, 'A pending "'.$notification->getTemplate()->getName().'" notification is locking this content');
            }
        }

        $em = $this->doctrine->getManager();
        if (null === $username) {
            $token = $this->tokenStorage->getToken();
            if (null === $token) {
                throw new \RuntimeException('Unexpected null token');
            }
            $lockerUsername = $token->getUsername();
        } else {
            $lockerUsername = $username;
        }

        if ($revision->isLockedFor($lockerUsername)) {
            throw new LockedException($revision);
        }

        if (!$username && !$this->container->get('app.twig_extension')->oneGranted($revision->giveContentType()->getFieldType()->getFieldsRoles(), $super)) {
            throw new PrivilegeException($revision);
        }
        //TODO: test circles

        $this->revRepository->lockRevision($revision->getId(), $lockerUsername, new \DateTime($this->lockTime));

        $revision->setLockBy($lockerUsername);
        if ($username) {
            //lock by a console script
            $revision->setLockUntil(new \DateTime('+30 seconds'));
        } else {
            $revision->setLockUntil(new \DateTime($this->lockTime));
        }
        $em->flush();

        return $lockerUsername;
    }

    public function getAllDeleted(ContentType $contentType)
    {
        return $this->revRepository->findBy([
            'deleted' => true,
            'contentType' => $contentType,
            'endTime' => null,
        ], [
            'modified' => 'asc',
        ]);
    }

    public function getDataCircles(Revision $revision)
    {
        $out = [];
        if ($revision->giveContentType()->getCirclesField()) {
            $fieldValue = $revision->getRawData()[$revision->giveContentType()->getCirclesField()];
            if (!empty($fieldValue)) {
                if (\is_array($fieldValue)) {
                    return $fieldValue;
                } else {
                    $out[] = $fieldValue;
                }
            }
        }

        return $out;
    }

    /**
     * @param string $ouuid
     *
     * @return Revision
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getRevisionByEnvironment($ouuid, ContentType $contentType, Environment $environment)
    {
        return $this->revRepository->findByEnvironment($ouuid, $contentType, $environment);
    }

    /**
     * @param string $ouuid|null
     *
     * @throws Throwable
     */
    public function propagateDataToComputedField(FormInterface $form, array &$objectArray, ContentType $contentType, string $type, ?string $ouuid, bool $migration = false, bool $finalize = true): bool
    {
        return $this->postProcessingService->postProcessing($form, $contentType, $objectArray, [
            '_id' => $ouuid,
            'migration' => $migration,
            'finalize' => $finalize,
        ]);
    }

    public function getPostProcessing(): PostProcessingService
    {
        return $this->postProcessingService;
    }

    public function getBusinessIds(array $keys): array
    {
        $items = [];
        $businessKeys = [];
        foreach ($keys as $key) {
            if (isset($this->cacheBusinessKey[$key])) {
                $businessKeys[$key] = $this->cacheBusinessKey[$key];
            } else {
                $link = EMSLink::fromText($key);
                $items[$link->getContentType()][] = $link->getOuuid();
                $businessKeys[$key] = $key;
            }
        }

        foreach ($items as $contentType => $ouuids) {
            $contentType = $this->contentTypeService->getByName($contentType);
            if ($contentType instanceof ContentType && $contentType->getBusinessIdField() && \count($ouuids) > 0) {
                $search = $this->elasticaService->convertElasticsearchSearch([
                    'index' => $contentType->getEnvironment()->getAlias(),
                    'body' => [
                        '_source' => $contentType->getBusinessIdField(),
                        'query' => [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            '_contenttype' => $contentType->getName(),
                                        ],
                                    ],
                                    [
                                        'terms' => [
                                            '_id' => $ouuids,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'size' => 100,
                ]);

                $scroll = $this->elasticaService->scroll($search, self::SCROLL_TIMEOUT);
                foreach ($scroll as $resultSet) {
                    foreach ($resultSet as $result) {
                        if (false === $result) {
                            continue;
                        }
                        $dataLink = $contentType->getName().':'.$result->getId();
                        $businessKeys[$dataLink] = $result->getSource()[$contentType->getBusinessIdField()] ?? $result->getId();
                        $this->cacheBusinessKey[$dataLink] = $businessKeys[$dataLink];
                    }
                }
            }
        }

        return \array_values($businessKeys);
    }

    public function getBusinessId(string $key): ?string
    {
        return $this->getBusinessIds([$key])[0] ?? $key;
    }

    public function hitToBusinessDocument(ContentType $contentType, array $hit)
    {
        $revision = $this->getEmptyRevision($contentType, null);
        $revision->setRawData($hit['_source']);
        $revision->setOuuid($hit['_id']);
        $revisionType = $this->formFactory->create(RevisionType::class, $revision, ['migration' => false, 'raw_data' => $revision->getRawData()]);
        $result = $this->walkRecursive($revisionType->get('data'), $hit['_source'], function (string $name, $data, DataFieldType $dataFieldType, DataField $dataField) {
            if (null !== $data) {
                if ($dataFieldType->isVirtual()) {
                    return $data;
                }

                if ($dataFieldType instanceof DataLinkFieldType) {
                    if (\is_string($data)) {
                        return [$name => $this->getBusinessId($data)];
                    }

                    return [$name => $this->getBusinessIds($data)];
                }

                return [$name => $data];
            }

            return [];
        });
        unset($revisionType);

        return new Document($contentType->getName(), $hit['_id'], $result);
    }

    public function walkRecursive(FormInterface $form, $rawData, callable $callback)
    {
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $form->getConfig()->getType()->getInnerType();
        /** @var DataField $dataField */
        $dataField = $form->getNormData();

        if (!$dataFieldType->isContainer()) {
            return $callback($form->getName(), $rawData, $dataFieldType, $dataField);
        }

        $output = [];
        if ($form instanceof IteratorAggregate) {
            /** @var FormInterface $child */
            foreach ($form->getIterator() as $child) {
                /** @var DataFieldType $childType */
                $childType = $child->getConfig()->getType()->getInnerType();
                if ($childType instanceof DataFieldType) {
                    $childData = $rawData;

                    $subDataField = $form->getNormData();
                    if ($subDataField instanceof DataField && null !== $subFieldType = $subDataField->getFieldType()) {
                        $subOptions = $subFieldType->getOptions();
                    } else {
                        $subOptions = [];
                    }
                    if (!$childType->isVirtual($subOptions ?? [])) {
                        $childData = $rawData[$child->getName()] ?? null;
                    }
                    $output = \array_merge($output, $this->walkRecursive($child, $childData, $callback));
                }
            }
        }

        return $callback($form->getName(), $output, $dataFieldType, $dataField);
    }

    public function convertInputValues(DataField $dataField)
    {
        foreach ($dataField->getChildren() as $child) {
            $this->convertInputValues($child);
        }
        if (!empty($dataField->getFieldType()) && !empty($dataField->getFieldType()->getType())) {
            /** @var DataFieldType $dataFieldType */
            $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
            if ($dataFieldType instanceof DataFieldType) {
                $dataFieldType->convertInput($dataField);
            } elseif (!DataService::isInternalField($dataField->getFieldType()->getName())) {
                $this->logger->warning('service.data.not_a_data_field', [
                    'field_name' => $dataField->getFieldType()->getName(),
                ]);
            }
        }
    }

    public static function isInternalField(string $fieldName)
    {
        return \in_array($fieldName, ['_ems_internal_deleted', 'remove_collection_item']);
    }

    public function generateInputValues(DataField $dataField)
    {
        foreach ($dataField->getChildren() as $child) {
            $this->generateInputValues($child);
        }
        if (!empty($dataField->getFieldType()) && !empty($dataField->getFieldType()->getType())) {
            $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
            if ($dataFieldType instanceof DataFieldType) {
                $dataFieldType->generateInput($dataField);
            } elseif (!DataService::isInternalField($dataField->getFieldType()->getName())) {
                $this->logger->warning('service.data.not_a_data_field', [
                    'field_name' => $dataField->getFieldType()->getName(),
                ]);
            }
        }
    }

    /**
     * @param string $ouuid
     * @param bool   $byARealUser
     *
     * @return Revision
     *
     * @throws Exception
     */
    public function createData($ouuid, array $rawdata, ContentType $contentType, $byARealUser = true)
    {
        $now = new \DateTime();
        $until = $now->add(new \DateInterval($byARealUser ? 'PT5M' : 'PT1M')); //+5 minutes
        $newRevision = new Revision();
        $newRevision->setContentType($contentType);
        $newRevision->setOuuid($ouuid);
        $newRevision->setStartTime($now);
        $newRevision->setEndTime(null);
        $newRevision->setDeleted(false);
        $newRevision->setDraft(true);
        if ($byARealUser) {
            $token = $this->tokenStorage->getToken();
            if (null === $token) {
                throw new \RuntimeException('Unexpected null token');
            }
            $newRevision->setLockBy($token->getUsername());
        } else {
            $newRevision->setLockBy('DATA_SERVICE');
        }
        $newRevision->setLockUntil($until);
        $newRevision->setRawData($rawdata);

        $em = $this->doctrine->getManager();
        if (!empty($ouuid)) {
            $revisionRepository = $em->getRepository('EMSCoreBundle:Revision');
            $anotherObject = $revisionRepository->findOneBy([
                    'contentType' => $contentType,
                    'ouuid' => $newRevision->getOuuid(),
                    'endTime' => null,
            ]);

            if (!empty($anotherObject)) {
                throw new ConflictHttpException('Duplicate OUUID '.$ouuid.' for this content type');
            }
        }

        $em->persist($newRevision);
        $em->flush();

        return $newRevision;
    }

    /**
     * @deprecated
     *
     * @param array $array
     * @param int   $sort_flags
     */
    public static function ksortRecursive(&$array, $sort_flags = SORT_REGULAR)
    {
        @\trigger_error('DataService::ksortRecursive is deprecated use the ArrayTool::normalizeArray instead', E_USER_DEPRECATED);

        ArrayTool::normalizeArray($array, $sort_flags);
    }

    /**
     * @param array<mixed> $objectArray
     */
    public function signRaw(array &$objectArray): string
    {
        if (isset($objectArray[Mapping::HASH_FIELD])) {
            unset($objectArray[Mapping::HASH_FIELD]);
        }
        if (isset($objectArray[Mapping::SIGNATURE_FIELD])) {
            unset($objectArray[Mapping::SIGNATURE_FIELD]);
        }
        if (isset($objectArray[Mapping::PUBLISHED_DATETIME_FIELD])) {
            unset($objectArray[Mapping::PUBLISHED_DATETIME_FIELD]);
        }
        ArrayTool::normalizeArray($objectArray);
        $json = \json_encode($objectArray);

        $hash = $this->storageManager->computeStringHash($json);
        $objectArray[Mapping::HASH_FIELD] = $hash;

        if ($this->private_key) {
            $signature = null;
            if (\openssl_sign($json, $signature, $this->private_key, OPENSSL_ALGO_SHA1)) {
                $objectArray[Mapping::SIGNATURE_FIELD] = \base64_encode($signature);
            } else {
                $this->logger->warning('service.data.not_able_to_sign', [
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => \openssl_error_string(),
                ]);
            }
        }

        return $hash;
    }

    public function sign(Revision $revision, $silentPublish = false)
    {
        if ($silentPublish && $revision->getAutoSave()) {
            $objectArray = $revision->getAutoSave();
        } else {
            $objectArray = $revision->getRawData();
        }

        $objectArray[Mapping::CONTENT_TYPE_FIELD] = $revision->giveContentType()->getName();
        if ($revision->hasVersionTags()) {
            $objectArray[Mapping::VERSION_UUID] = $revision->getVersionUuid();
            $objectArray[Mapping::VERSION_TAG] = $revision->getVersionTag();
        }

        $hash = $this->signRaw($objectArray);
        $revision->setSha1($hash);

        $revision->setRawData($objectArray);

        return $objectArray;
    }

    public function getPublicKey()
    {
        if ($this->private_key && empty($this->public_key)) {
            $certificate = \openssl_pkey_get_private($this->private_key);
            if (false === $certificate) {
                throw new \RuntimeException('Private key not found');
            }
            $details = \openssl_pkey_get_details($certificate);
            $this->public_key = $details['key'];
        }

        return $this->public_key;
    }

    public function getCertificateInfo()
    {
        if ($this->private_key) {
            $certificate = \openssl_pkey_get_private($this->private_key);
            if (false === $certificate) {
                throw new \RuntimeException('Private key not found');
            }

            return \openssl_pkey_get_details($certificate);
        }

        return null;
    }

    public function testIntegrityInIndexes(Revision $revision)
    {
        $this->sign($revision);
        $contentType = $revision->getContentType();
        if (null === $contentType) {
            throw new \RuntimeException('Unexpected null content type');
        }

        if ($revision->getModified() > new \DateTime('-10 seconds')) {
            return;
        }

        foreach ($revision->getEnvironments() as $environment) {
            try {
                $document = $this->searchService->getDocument($contentType, $revision->getOuuid(), $environment);
                $indexedItem = $document->getSource();

                ArrayTool::normalizeArray($indexedItem);

                if (isset($indexedItem[Mapping::PUBLISHED_DATETIME_FIELD])) {
                    unset($indexedItem[Mapping::PUBLISHED_DATETIME_FIELD]);
                }

                if (isset($indexedItem[Mapping::HASH_FIELD])) {
                    if ($indexedItem[Mapping::HASH_FIELD] != $revision->getSha1()) {
                        $this->logger->warning('service.data.hash_mismatch', [
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            'index_hash' => $indexedItem[Mapping::HASH_FIELD],
                            'db_hash' => $revision->getSha1(),
                            'label' => $revision->getLabel(),
                        ]);
                    }
                    unset($indexedItem[Mapping::HASH_FIELD]);

                    if (isset($indexedItem[Mapping::SIGNATURE_FIELD])) {
                        $binary_signature = \base64_decode($indexedItem[Mapping::SIGNATURE_FIELD]);
                        unset($indexedItem[Mapping::SIGNATURE_FIELD]);
                        $data = \json_encode($indexedItem);

                        // Check signature
                        $ok = \openssl_verify($data, $binary_signature, $this->getPublicKey(), self::ALGO);
                        if (0 === $ok) {
                            $this->logger->info('service.data.check_signature_failed', [
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getLabel(),
                                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                                'label' => $revision->getLabel(),
                            ]);
                        } elseif (1 !== $ok) { //1 means signature is ok
                            $this->logger->info('service.data.error_check_signature', [
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                                EmsFields::LOG_ERROR_MESSAGE_FIELD => \openssl_error_string(),
                                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                                'label' => $revision->getLabel(),
                            ]);
                        }
                    } else {
                        $data = \json_encode($indexedItem);
                        if ($this->private_key) {
                            $this->logger->info('service.data.revision_not_signed', [
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                                'label' => $revision->getLabel(),
                            ]);
                        }
                    }

                    $computedHash = $this->storageManager->computeStringHash($data);
                    if ($computedHash !== $revision->getSha1()) {
                        $this->logger->info('service.data.computed_hash_mismatch', [
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            'computed_hash' => $computedHash,
                            'db_hash' => $revision->getSha1(),
                            'label' => $revision->getLabel(),
                        ]);
                    }
                } else {
                    $this->logger->warning('service.data.hash_missing', [
                        EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                        EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                        'label' => $revision->getLabel(),
                    ]);
                }
            } catch (Exception $e) {
                $this->logger->error('service.data.integrity_failed', [
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    'label' => $revision->getLabel(),
                ]);
            }
        }
    }

    /**
     * @return FormInterface
     *
     * @throws Exception
     */
    public function buildForm(Revision $revision)
    {
        if (null == $revision->getDatafield()) {
            $this->loadDataStructure($revision);
        }

        //Get the form from Factory
        $builder = $this->formFactory->createBuilder(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);
        $form = $builder->getForm();

        return $form;
    }

    /**
     * Try to finalize a revision.
     *
     * @param FormInterface $form
     * @param string        $username
     * @param bool          $computeFields (allow to sky computedFields compute, i.e during a post-finalize)
     *
     * @return Revision
     *
     * @throws DataStateException
     * @throws Exception
     * @throws Throwable
     */
    public function finalizeDraft(Revision $revision, ?FormInterface &$form = null, ?string $username = null, bool $computeFields = true)
    {
        if ($revision->getDeleted()) {
            throw new Exception('Can not finalized a deleted revision');
        }
        if (null == $form) {
            if (null == $revision->getDatafield()) {
                $this->loadDataStructure($revision);
            }

            //Get the form from Factory
            $builder = $this->formFactory->createBuilder(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);
            $form = $builder->getForm();
        }

        if (empty($username)) {
            $token = $this->tokenStorage->getToken();
            if (null === $token) {
                throw new \RuntimeException('Unexpected null token');
            }
            $username = $token->getUsername();
        }
        $this->lockRevision($revision, null, false, $username);

        $em = $this->doctrine->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');

        //TODO: test if draft and last version publish in

        if (!empty($revision->getAutoSave())) {
            throw new DataStateException('An auto save is pending, it can not be finalized.');
        }

        if (!$revision->hasOuuid() && $this->preGeneratedOuuids) {
            $revision->setOuuid(Uuid::uuid4()->toString());
        }

        $objectArray = $revision->getRawData();

        $this->updateDataStructure($revision->giveContentType()->getFieldType(), $form->get('data')->getNormData());
        try {
            if ($computeFields && $this->propagateDataToComputedField($form->get('data'), $objectArray, $revision->giveContentType(), $revision->giveContentType()->getName(), $revision->getOuuid())) {
                $revision->setRawData($objectArray);
            }
        } catch (CantBeFinalizedException $e) {
            $form->addError(new FormError($e->getMessage()));
        }

        $previousData = null;

        $revision->setRawDataFinalizedBy($username);

        $objectArray = $this->sign($revision);

        if (empty($form) || $this->isValid($form, null, $objectArray)) {
            $ouuid = $revision->getOuuid();
            $this->indexService->indexRevision($revision);
            if (null !== $ouuid) {
                $item = $repository->findByOuuidContentTypeAndEnvironment($revision);
                if ($item) {
                    $this->lockRevision($item, null, false, $username);
                    $previousData = $item->getData();
                    $item->removeEnvironment($revision->giveContentType()->giveEnvironment());
                    $em->persist($item);
                    $this->unlockRevision($item, $username);
                }
            }

            $revision->addEnvironment($revision->giveContentType()->giveEnvironment());
            $revision->setDraft(false);

            $revision->setFinalizedBy($username);

            $em->persist($revision);
            $em->flush();

            $this->unlockRevision($revision, $username);
            $this->dispatcher->dispatch(RevisionFinalizeDraftEvent::NAME, new RevisionFinalizeDraftEvent($revision));

            $this->auditLogger->notice('log.revision.finalized', LogRevisionContext::update($revision));

            try {
                $this->postFinalizeTreatment($revision, $form->get('data'), $previousData);
            } catch (Exception $e) {
                $this->logger->warning('service.data.post_finalize_failed', [
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_ENVIRONMENT_FIELD => $revision->giveContentType()->giveEnvironment()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    'label' => $revision->getLabel(),
                ]);
            }
        } else {
            $this->logFormErrors($revision, $form);
        }

        return $revision;
    }

    private function logFormErrors(Revision $revision, FormInterface $form)
    {
        $formErrors = $form->getErrors(true, true);
        /** @var FormError $formError */
        foreach ($formErrors as $formError) {
            $fieldForm = $formError->getOrigin();
            $dataField = null;
            while (null !== $fieldForm && !$fieldForm->getNormData() instanceof DataField) {
                $fieldForm = $fieldForm->getParent();
            }

            if (!$fieldForm instanceof FormInterface || !$fieldForm->getNormData() instanceof DataField) {
                continue;
            }
            /** @var DataField $dataField */
            $dataField = $fieldForm->getNormData();
            if (empty($dataField->getMessages())) {
                continue;
            }
            if (1 === \sizeof($dataField->getMessages())) {
                $errorMessage = $dataField->getMessages()[0];
            } else {
                $errorMessage = \sprintf('["%s"]', \implode('","', $dataField->getMessages()));
            }

            $fieldName = $fieldForm->getNormData()->getFieldType()->getDisplayOption('label', $fieldForm->getNormData()->getFieldType()->getName());
            $errorPath = '';

            $parent = $fieldForm;
            while (($parent = $parent->getParent()) !== null) {
                if ($parent->getNormData() instanceof DataField && null !== $parent->getNormData()->getFieldType()->getParent()) {
                    $errorPath .= $parent->getNormData()->getFieldType()->getDisplayOption('label', $parent->getNormData()->getFieldType()->getName()).' > ';
                }
            }
            $errorPath .= $fieldName;

            $this->logger->warning('service.data.error_with_fields', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $errorMessage,
                EmsFields::LOG_FIELD_IN_ERROR_FIELD => $fieldName,
                EmsFields::LOG_PATH_IN_ERROR_FIELD => $errorPath,
            ]);
        }
    }

    /**
     * Loop over all fields and call postFinalizeTreatment.
     *
     * @param ?array<string, mixed> $previousData
     */
    public function postFinalizeTreatment(Revision $revision, FormInterface $form, ?array $previousData)
    {
        foreach ($form->all() as $subForm) {
            if ($subForm->getNormData() instanceof DataField) {
                /** @var DataFieldType $dataFieldType */
                $dataFieldType = $subForm->getConfig()->getType()->getInnerType();
                $childrenPreviousData = $dataFieldType->postFinalizeTreatment($revision, $subForm->getNormData(), $previousData);
                $this->postFinalizeTreatment($revision, $subForm, $childrenPreviousData);
            }
        }
    }

    /**
     * @param string $type
     * @param string $ouuid
     *
     * @return Revision
     *
     * @throws Exception
     * @throws NotFoundHttpException
     */
    public function getNewestRevision($type, $ouuid)
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var ContentTypeRepository $contentTypeRepo */
        $contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');
        $contentTypes = $contentTypeRepo->findBy([
                'name' => $type,
                'deleted' => false,
        ]);

        if (1 != \count($contentTypes)) {
            throw new NotFoundHttpException('Unknown content type');
        }
        $contentType = $contentTypes[0];

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');

        /** @var Revision $revision */
        $revisions = $repository->findBy([
                'ouuid' => $ouuid,
                'endTime' => null,
                'contentType' => $contentType,
                'deleted' => false,
        ]);

        if (1 == \count($revisions)) {
            if ($revisions[0] instanceof Revision && null == $revisions[0]->getEndTime()) {
                return $revisions[0];
            } else {
                throw new NotFoundHttpException('Revision for ouuid '.$ouuid.' and contenttype '.$type.' with end time '.$revisions[0]->getEndTime());
            }
        } elseif (0 == \count($revisions)) {
            throw new NotFoundHttpException('Revision not found for ouuid '.$ouuid.' and contenttype '.$type);
        } else {
            throw new Exception('Too much newest revisions available for ouuid '.$ouuid.' and contenttype '.$type);
        }
    }

    /**
     * @param array<mixed>|null $rawData
     *
     * @return Revision
     *
     * @throws DuplicateOuuidException
     * @throws HasNotCircleException
     * @throws Throwable
     */
    public function newDocument(ContentType $contentType, ?string $ouuid = null, ?array $rawData = null, ?string $username = null)
    {
        $this->hasCreateRights($contentType);
        /** @var RevisionRepository $revisionRepository */
        $revisionRepository = $this->em->getRepository('EMSCoreBundle:Revision');

        $revision = new Revision();

        if (null !== $ouuid && $revisionRepository->countRevisions($ouuid, $contentType)) {
            throw new DuplicateOuuidException();
        }

        if (!empty($contentType->getDefaultValue())) {
            try {
                $template = $this->twig->createTemplate($contentType->getDefaultValue());
                $defaultValue = $template->render([
                    'environment' => $contentType->getEnvironment(),
                    'contentType' => $contentType,
                    'currentUser' => $this->userService->isCliSession() ? null : $this->userService->getCurrentUser(),
                ]);
                try {
                    $revision->setRawData(Json::decode($defaultValue));
                } catch (\Throwable $e) {
                    $this->logger->error('service.data.default_value_error', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                        EmsFields::LOG_OUUID_FIELD => $ouuid,
                    ]);
                }
            } catch (\Twig\Error\Error $e) {
                $this->logger->error('service.data.default_value_template_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    EmsFields::LOG_OUUID_FIELD => $ouuid,
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                ]);
            }
        }

        if ($rawData) {
            $rawData = \array_diff_key($rawData, Mapping::MAPPING_INTERNAL_FIELDS);

            if ($revision->getRawData()) {
                $revision->setRawData(\array_replace_recursive($rawData, $revision->getRawData()));
            } else {
                $revision->setRawData($rawData);
            }
        }

        if (null === $username) {
            $username = $this->userService->getCurrentUser()->getUsername();
        }
        $currentUser = $this->userService->isCliSession() ? null : $this->userService->getCurrentUser();

        $now = new \DateTime('now');
        $revision->setContentType($contentType);
        $revision->setDraft(true);
        $revision->setOuuid($ouuid);
        $revision->setDeleted(false);
        $revision->setStartTime($now);
        $revision->setEndTime(null);
        $revision->setLockBy($username);
        $revision->setLockUntil(new \DateTime($this->lockTime));

        $ownerRole = $contentType->getOwnerRole();
        if (null !== $currentUser && null !== $ownerRole && $this->userService->isGrantedRole($ownerRole)) {
            $revision->setOwner($currentUser->getUsername());
        }

        if (null !== $currentUser && $contentType->getCirclesField()) {
            if (isset($revision->getRawData()[$contentType->getCirclesField()])) {
                if (\is_array($revision->getRawData()[$contentType->getCirclesField()])) {
                    $revision->setCircles($revision->getRawData()[$contentType->getCirclesField()]);
                } else {
                    $revision->setCircles([$revision->getRawData()[$contentType->getCirclesField()]]);
                }
            } else {
                $fieldType = $contentType->getFieldType()->getChildByPath($contentType->getCirclesField());
                if ($fieldType) {
                    $options = $fieldType->getDisplayOptions();
                    if (isset($options['multiple']) && $options['multiple']) {
                        $revision->setRawData(\array_merge($revision->getRawData(), [$contentType->getCirclesField() => $currentUser->getCircles()]));
                        $revision->setCircles($currentUser->getCircles());
                    } else {
                        //set first of my circles
                        if (!empty($currentUser->getCircles())) {
                            $revision->setRawData(\array_merge($revision->getRawData(), [$contentType->getCirclesField() => $currentUser->getCircles()[0]]));
                            $revision->setCircles([$currentUser->getCircles()[0]]);
                        }
                    }
                }
            }
        }
        $this->setMetaFields($revision);

        $this->em->persist($revision);
        $this->em->flush();

        return $revision;
    }

    /**
     * @throws HasNotCircleException
     */
    public function hasCreateRights(ContentType $contentType)
    {
        if ($this->userService->isCliSession()) {
            return;
        }
        $userCircles = $this->userService->getCurrentUser()->getCircles();
        $environment = $contentType->getEnvironment();
        $environmentCircles = $environment->getCircles();
        if (!$this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT') && !empty($environmentCircles)) {
            if (empty($userCircles)) {
                throw new HasNotCircleException($environment);
            }
            $found = false;
            foreach ($userCircles as $userCircle) {
                if (\in_array($userCircle, $environmentCircles)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new HasNotCircleException($environment);
            }
        }
    }

    public function setMetaFields(Revision $revision)
    {
        $this->setCircles($revision);
        $this->setLabelField($revision);

        $revision->setVersionMetaFields();
    }

    private function setCircles(Revision $revision)
    {
        $objectArray = $revision->getRawData();
        if (!empty($revision->giveContentType()->getCirclesField()) && isset($objectArray[$revision->giveContentType()->getCirclesField()]) && !empty($objectArray[$revision->giveContentType()->getCirclesField()])) {
            $revision->setCircles(\is_array($objectArray[$revision->giveContentType()->getCirclesField()]) ? $objectArray[$revision->giveContentType()->getCirclesField()] : [$objectArray[$revision->giveContentType()->getCirclesField()]]);
        } else {
            $revision->setCircles(null);
        }
    }

    private function setLabelField(Revision $revision)
    {
        $objectArray = $revision->getRawData();
        $labelField = $revision->giveContentType()->getLabelField();
        if (!empty($labelField) &&
                isset($objectArray[$labelField]) &&
                !empty($objectArray[$labelField])) {
            $revision->setLabelField($objectArray[$labelField]);
        } else {
            $revision->setLabelField(null);
        }
    }

    /**
     * @param string        $type
     * @param string        $ouuid
     * @param Revision|null $fromRev
     * @param string|null   $username
     *
     * @return Revision
     *
     * @throws LockedException
     * @throws PrivilegeException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function initNewDraft($type, $ouuid, $fromRev = null, $username = null)
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var ContentTypeRepository $contentTypeRepo */
        $contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');
        /** @var ContentType|null $contentType */
        $contentType = $contentTypeRepo->findOneBy([
                'name' => $type,
                'deleted' => false,
        ]);

        if (null === $contentType) {
            throw new NotFoundHttpException('ContentType '.$type.' Not found');
        }

        $revision = $this->getNewestRevision($type, $ouuid);
        $revision->setDeleted(false);
        if (null !== $revision->getDataField()) {
            $revision->getDataField()->propagateOuuid($revision->getOuuid());
        }

        $this->setMetaFields($revision);

        $this->lockRevision($revision, null, false, $username);

        if (!$revision->getDraft()) {
            $now = new \DateTime();

            if ($fromRev) {
                $newDraft = new Revision($fromRev);
            } else {
                $newDraft = new Revision($revision);
            }

            $newDraft->setStartTime($now);
            $revision->setEndTime($now);
            $newDraft->setDraftSaveDate(null);
            $revision->clearTasks();

            $lockedBy = $this->lockRevision($newDraft, null, false, $username);
            $newDraft->setAutoSaveBy($lockedBy);

            $em->persist($revision);
            $em->persist($newDraft);
            $em->flush();

            $this->auditLogger->info('log.revision.draft.created', LogRevisionContext::update($revision));

            $this->dispatcher->dispatch(RevisionNewDraftEvent::NAME, new RevisionNewDraftEvent($newDraft));

            return $newDraft;
        }

        return $revision;
    }

    /**
     * @throws LockedException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws PrivilegeException
     */
    public function discardDraft(Revision $revision, $super = false, $username = null): ?int
    {
        $this->lockRevision($revision, null, $super, $username);

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');

        if (!$revision->getDraft() || null != $revision->getEndTime()) {
            throw new BadRequestHttpException('Only authorized on a draft');
        }

        $hasPreviousRevision = 0;

        if (null != $revision->getOuuid()) {
            /** @var QueryBuilder $qb */
            $qb = $repository->createQueryBuilder('t')
                ->where('t.ouuid = :ouuid')
                ->andWhere('t.id <> :id')
                ->andWhere('t.contentType =  :contentType')
                ->orderBy('t.id', 'desc')
                ->setParameter('ouuid', $revision->getOuuid())
                ->setParameter('contentType', $revision->getContentType())
                ->setParameter('id', $revision->getId())
                ->setMaxResults(1);
            $query = $qb->getQuery();

            $result = $query->getResult();

            if (1 == \count($result)) {
                /** @var Revision $previous */
                $previous = $result[0];
                $this->lockRevision($previous, null, $super, $username);
                $previous->setEndTime(null);
                if ($previous->getEnvironments()->isEmpty()) {
                    $previous->setDraft(true);
                }
                $hasPreviousRevision = $previous->getId();
                $em->persist($previous);
            }
        }

        $em->remove($revision);

        $em->flush();

        $this->auditLogger->info('log.revision.draft.deleted', LogRevisionContext::update($revision));

        return $hasPreviousRevision;
    }

    /**
     * @param string $type
     * @param string $ouuid
     */
    public function delete($type, $ouuid)
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var ContentTypeRepository $contentTypeRepo */
        $contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');

        $contentTypes = $contentTypeRepo->findBy([
                'deleted' => false,
                'name' => $type,
        ]);
        if (!$contentTypes || 1 != \count($contentTypes)) {
            throw new NotFoundHttpException('Content Type not found');
        }

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');

        $revisions = $repository->findBy([
                'ouuid' => $ouuid,
                'contentType' => $contentTypes[0],
        ]);

        /** @var Revision $revision */
        foreach ($revisions as $revision) {
            $this->lockRevision($revision);

            /** @var Environment $environment */
            foreach ($revision->getEnvironments() as $environment) {
                try {
                    $this->indexService->delete($revision, $environment);
                    $this->auditLogger->notice('log.unpublished.success', LogRevisionContext::unpublish($revision, $environment));
                } catch (NotFoundException $e) {
                    if (!$revision->getDeleted()) {
                        $this->logger->warning('service.data.already_unpublished', [
                            'label' => $revision->getLabel(),
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
                            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getLabel(),
                        ]);
                    }
                    throw $e;
                }
                $revision->removeEnvironment($environment);
            }
            $revision->setDeleted(true);
            $revision->setDeletedBy($this->tokenStorage->getToken()->getUsername());

            if (null === $revision->getEndTime()) {
                $this->auditLogger->notice('log.revision.deleted', LogRevisionContext::delete($revision));
            }

            $em->persist($revision);
        }
        $em->flush();
    }

    /**
     * @param string $ouuid
     *
     * @throws LockedException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws PrivilegeException
     */
    public function emptyTrash(ContentType $contentType, $ouuid)
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');

        $revisions = $repository->findBy([
                'ouuid' => $ouuid,
                'contentType' => $contentType,
                'deleted' => true,
        ]);

        /** @var Revision $revision */
        foreach ($revisions as $revision) {
            $this->lockRevision($revision);
            $em->remove($revision);
        }
        $em->flush();
    }

    /**
     * @param string $ouuid
     *
     * @return int|null
     *
     * @throws LockedException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws PrivilegeException
     */
    public function putBack(ContentType $contentType, $ouuid)
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');

        $revisions = $repository->findBy([
                'ouuid' => $ouuid,
                'contentType' => $contentType,
                'deleted' => true,
        ]);

        $out = null;
        /** @var Revision $revision */
        foreach ($revisions as $revision) {
            $this->lockRevision($revision);
            $revision->setDeleted(false);
            $revision->setDeletedBy(null);
            if (null === $revision->getEndTime()) {
                $revision->setDraft(true);
                $out = $revision->getId();
                $this->auditLogger->notice('log.revision.restored', LogRevisionContext::update($revision));
            }
            $em->persist($revision);
        }
        $em->flush();

        return $out;
    }

    /**
     * @throws Exception
     */
    public function updateDataStructure(FieldType $meta, DataField $dataField)
    {
        //no need to generate the structure for subfields
        $isContainer = true;

        if (null !== $dataField->getFieldType()) {
            $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();

            if ($dataFieldType instanceof DataFieldType) {
                $isContainer = $dataFieldType->isContainer();
            } elseif (!DataService::isInternalField($dataField->getFieldType()->getName())) {
                $this->logger->warning('service.data.not_a_data_field', [
                    'field_name' => $dataField->getFieldType()->getName(),
                ]);
            }
        }

        if ($isContainer) {
            /** @var FieldType $field */
            foreach ($meta->getChildren() as $key => $field) {
                //no need to generate the structure for delete field
                if (!$field->getDeleted()) {
                    $child = $dataField->__get('ems_'.$field->getName());
                    if (null == $child) {
                        $child = new DataField();
                        $child->setFieldType($field);
                        $child->setOrderKey($field->getOrderKey());
                        $child->setParent($dataField);
                        $dataField->addChild($child, $key);
                        if (isset($field->getDisplayOptions()['defaultValue'])) {
                            $child->setEncodedText($field->getDisplayOptions()['defaultValue']);
                        }
                    }
                    if (0 != \strcmp($field->getType(), CollectionFieldType::class)) {
                        $this->updateDataStructure($field, $child);
                    }
                }
            }
        }
    }

    /**
     * Assign data in dataValues based on the elastic index content.
     *
     * @param bool $isMigration
     */
    public function updateDataValue(DataField $dataField, array &$elasticIndexDatas, $isMigration = false)
    {
        $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
        if ($dataFieldType instanceof DataFieldType) {
            $fieldType = $dataField->getFieldType();
            if (null === $fieldType) {
                throw new \RuntimeException('Unexpected null fieldType');
            }

            $fieldNames = $dataFieldType->getJsonNames($fieldType);
            if (0 === \count($fieldNames)) {//Virtual container
                /** @var DataField $child */
                foreach ($dataField->getChildren() as $child) {
                    $this->updateDataValue($child, $elasticIndexDatas, $isMigration);
                }
            } else {
                if ($dataFieldType->isVirtual($dataField->getFieldType()->getOptions())) {
                    $treatedFields = $dataFieldType->importData($dataField, $elasticIndexDatas, $isMigration);
                    foreach ($treatedFields as $fieldName) {
                        unset($elasticIndexDatas[$fieldName]);
                    }
                } else {
                    foreach ($fieldNames as $fieldName) {
                        if (\array_key_exists($fieldName, $elasticIndexDatas)) {
                            $treatedFields = $dataFieldType->importData($dataField, $elasticIndexDatas[$fieldName], $isMigration);
                            foreach ($treatedFields as $fieldName) {
                                unset($elasticIndexDatas[$fieldName]);
                            }
                        }
                    }
                }
            }
        } elseif (!DataService::isInternalField($dataField->getFieldType()->getName())) {
            $this->logger->warning('service.data.not_a_data_field', [
                'field_name' => $dataField->getFieldType()->getName(),
            ]);
        }
    }

    /**
     * @throws Exception
     */
    public function loadDataStructure(Revision $revision)
    {
        $data = new DataField();
        $data->setFieldType($revision->giveContentType()->getFieldType());
        $data->setOrderKey($revision->giveContentType()->getFieldType()->getOrderKey());
        $data->setRawData($revision->getRawData());
        $revision->setDataField($data);
        $this->updateDataStructure($revision->giveContentType()->getFieldType(), $revision->getDataField());
        //$revision->getDataField()->updateDataStructure($this->formRegistry, $revision->getContentType()->getFieldType());
        $object = $revision->getRawData();
        $this->updateDataValue($data, $object);
        unset($object[Mapping::CONTENT_TYPE_FIELD]);
        unset($object[Mapping::HASH_FIELD]);
        unset($object[Mapping::FINALIZED_BY_FIELD]);
        unset($object[Mapping::FINALIZATION_DATETIME_FIELD]);
        unset($object[Mapping::VERSION_TAG]);
        unset($object[Mapping::VERSION_UUID]);
        if (\count($object) > 0) {
            $html = DataService::arrayToHtml($object);

            $this->logger->warning('service.data.data_not_consumed', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
                'count' => \count($object),
                'data' => $html,
            ]);
        }
    }

    /**
     * @return array
     *
     * @throws Throwable
     */
    public function reloadData(Revision $revision)
    {
        $finalizedBy = false;
        $finalizationDate = false;
        $objectArray = $revision->getRawData();
        if (isset($objectArray[Mapping::FINALIZED_BY_FIELD])) {
            $finalizedBy = $objectArray[Mapping::FINALIZED_BY_FIELD];
        }
        if (isset($objectArray[Mapping::FINALIZATION_DATETIME_FIELD])) {
            $finalizationDate = $objectArray[Mapping::FINALIZATION_DATETIME_FIELD];
        }

        $builder = $this->formFactory->createBuilder(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);
        $form = $builder->getForm();

        $objectArray = $revision->getRawData();
        $this->updateDataStructure($revision->giveContentType()->getFieldType(), $form->get('data')->getNormData());
        $this->propagateDataToComputedField($form->get('data'), $objectArray, $revision->giveContentType(), $revision->giveContentType()->getName(), $revision->getOuuid());

        if (false !== $finalizedBy) {
            $objectArray[Mapping::FINALIZED_BY_FIELD] = $finalizedBy;
        }
        if (false !== $finalizationDate) {
            $objectArray[Mapping::FINALIZATION_DATETIME_FIELD] = $finalizationDate;
        }

        $revision->setRawData($objectArray);

        return $objectArray;
    }

    public function getSubmitData(FormInterface $form)
    {
        $out = $form->getViewData();

        if ($form instanceof Form) {
            $iteratedOn = $form->getIterator();
        } else {
            $iteratedOn = $form->all();
        }

        /** @var FormInterface $subForm */
        foreach ($iteratedOn as $subForm) {
            if ($subForm->getConfig()->getCompound()) {
                $out[$subForm->getName()] = $this->getSubmitData($subForm);
            }
        }

        return $out;
    }

    /**
     * @param string $user
     *
     * @return Revision
     *
     * @throws Exception
     */
    public function getEmptyRevision(ContentType $contentType, $user)
    {
        $now = new \DateTime();
        $until = $now->add(new \DateInterval('PT5M')); //+5 minutes
        $newRevision = new Revision();
        $newRevision->setContentType($contentType);
        $newRevision->addEnvironment($contentType->getEnvironment());
        $newRevision->setStartTime($now);
        $newRevision->setEndTime(null);
        $newRevision->setDeleted(false);
        $newRevision->setDraft(true);
        $newRevision->setLockBy($user);
        $newRevision->setLockUntil($until);
        $newRevision->setRawData([]);

        return $newRevision;
    }

    public static function arrayToHtml(array $array)
    {
        $out = '<ul>';
        foreach ($array as $id => $item) {
            $out .= '<li>'.$id.':';
            if (\is_array($item)) {
                $out .= DataService::arrayToHtml($item);
            } else {
                $out .= $item;
            }
            $out .= '</li>';
        }

        return $out.'</ul>';
    }

    /**
     * @param null $masterRawData
     *
     * @return bool
     *
     * @throws Exception
     */
    public function isValid(FormInterface &$form, DataField $parent = null, &$masterRawData = null)
    {
        $viewData = $form->getNormData();

        if ($viewData instanceof Revision) {
            $topLevelDataFieldForm = $form->get('data');

            return $this->isValid($topLevelDataFieldForm, $parent, $masterRawData);
        }

        if (!$viewData instanceof DataField) {
            if (!DataService::isInternalField($form->getName())) {
                $this->logger->warning('service.data.not_a_data_field', [
                    'field_name' => $form->getName(),
                ]);
            }

            return true;
        }

        $dataField = $viewData;

        $dataFieldType = null;
        if (null !== $dataField->getFieldType() && null !== $dataField->getFieldType()->getType()) {
            /** @var DataFieldType $dataFieldType */
            $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
            $dataFieldType->isValid($dataField, $parent, $masterRawData);
        }
        $isValid = true;
        if (null !== $dataFieldType && $dataFieldType->isContainer()) {//If dataField is container or type is null => Container => Recursive
            $formChildren = $form->all();
            foreach ($formChildren as $child) {
                if ($child instanceof FormInterface) {
                    $tempIsValid = $this->isValid($child, $dataField, $masterRawData); //Recursive
                    $isValid = $isValid && $tempIsValid;
                }
            }
            if (!$isValid) {
                $form->addError(new FormError('At least one field is not valid!'));
            }
        }
        if (null !== $dataFieldType && !$dataFieldType->isValid($dataField, $parent, $masterRawData)) {
            $isValid = false;
            $form->addError(new FormError('This Field is not valid! '.$dataField->getMessages()[0]));
        }

        if ($form->getErrors(true, true)->count() > 0) {
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * @param int $id
     *
     * @return Revision
     *
     * @throws Exception
     */
    public function getRevisionById($id, ContentType $type)
    {
        $em = $this->doctrine->getManager();

        /** @var ContentTypeRepository $contentTypeRepo */
        $contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');
        $contentTypes = $contentTypeRepo->findBy([
                'name' => $type->getName(),
                'deleted' => false,
        ]);

        if (1 != \count($contentTypes)) {
            throw new NotFoundHttpException('Unknown content type');
        }
        $contentType = $contentTypes[0];
        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision $revision */
        $revisions = $repository->findBy([
                'id' => $id,
                'endTime' => null,
                'contentType' => $contentType,
                'deleted' => false,
        ]);

        if (1 == \count($revisions)) {
            if ($revisions[0] instanceof Revision && null == $revisions[0]->getEndTime()) {
                $revision = $revisions[0];

                return $revision;
            } else {
                throw new Exception('Revision for ouuid '.$id.' and contenttype '.$type.' with end time '.$revisions[0]->getEndTime());
            }
        } elseif (0 == \count($revisions)) {
            throw new NotFoundHttpException('Revision not found for id '.$id.' and contenttype '.$type);
        } else {
            throw new Exception('Too much newest revisions available for ouuid '.$id.' and contenttype '.$type);
        }
    }

    /**
     * @param string $replaceOrMerge
     *
     * @return Revision
     *
     * @throws LockedException
     * @throws PrivilegeException
     */
    public function replaceData(Revision $revision, array $rawData, $replaceOrMerge = 'replace')
    {
        if (!$revision->getDraft()) {
            $em = $this->doctrine->getManager();
            $this->lockRevision($revision);

            $now = new \DateTime();

            $newDraft = new Revision($revision);

            if ('replace' === $replaceOrMerge) {
                $newDraft->setRawData($rawData);
            } elseif ('merge' === $replaceOrMerge) {
                $newRawData = \array_merge($revision->getRawData(), $rawData);
                $newDraft->setRawData($newRawData);
            } else {
                $this->logger->error('service.data.unknown_update_type', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
                    'update_type' => $replaceOrMerge,
                    'label' => $revision->getLabel(),
                ]);

                return $revision;
            }

            $this->setMetaFields($newDraft);

            $newDraft->setStartTime($now);
            $revision->setEndTime($now);

            $this->lockRevision($newDraft);

            $em->persist($revision);
            $em->persist($newDraft);
            $em->flush();

            return $newDraft;
        } else {
            $this->logger->error('service.data.not_a_draft', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
                'update_type' => $replaceOrMerge,
                'label' => $revision->getLabel(),
            ]);
        }

        return $revision;
    }

    public function getDataFieldsStructure(FormInterface $form)
    {
        /** @var DataField $out */
        $out = $form->getNormData();
        foreach ($form as $key => $item) {
            if ($item->getNormData() instanceof DataField) {
                $out->addChild($item->getNormData(), $key);
                $this->getDataFieldsStructure($item);
            }
        }

        return $out;
    }

    public function createAndMapIndex(Environment $environment): void
    {
        $body = $this->environmentService->getIndexAnalysisConfiguration();
        $indexName = $environment->getNewIndexName();
        $this->mapping->createIndex($indexName, $body, $environment->getAlias());

        foreach ($this->contentTypeService->getAll() as $contentType) {
            $this->contentTypeService->updateMapping($contentType, $indexName);
        }
    }

    public function getDataLinks(string $contentTypesCommaList, array $businessIds): array
    {
        $items = [];
        $ouuids = [];
        foreach ($businessIds as $businessId) {
            if (isset($this->cacheOuuids[$contentTypesCommaList][$businessId])) {
                $ouuids[$businessId] = $this->cacheOuuids[$contentTypesCommaList][$businessId];
            } else {
                $items[] = $businessId;
                $ouuids[$businessId] = $businessId;
            }
        }

        foreach (\explode(',', $contentTypesCommaList) as $contentTypeName) {
            $contentType = $this->contentTypeService->getByName($contentTypeName);
            if (false === $contentType) {
                $this->logger->warning('log.service.data.get_data_links.content_type_not_found', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentTypeName,
                ]);
                continue;
            }

            if ($contentType->getBusinessIdField() && \count($ouuids) > 0) {
                $search = $this->elasticaService->convertElasticsearchSearch([
                    'index' => $contentType->getEnvironment()->getAlias(),
                    'body' => [
                        'size' => \sizeof($ouuids),
                        '_source' => $contentType->getBusinessIdField(),
                        'query' => [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            '_contenttype' => $contentType->getName(),
                                        ],
                                    ],
                                    [
                                        'terms' => [
                                            $contentType->getBusinessIdField() => $items,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'size' => 100,
                ]);

                $scroll = $this->elasticaService->scroll($search, self::SCROLL_TIMEOUT);
                foreach ($scroll as $resultSet) {
                    foreach ($resultSet as $result) {
                        if (false === $result) {
                            continue;
                        }
                        $key = \sprintf('%s:%s', $contentType->getName(), $result->getId());
                        $ouuids[$result->getSource()[$contentType->getBusinessIdField()]] = $key;
                        $this->cacheOuuids[$contentTypesCommaList][$contentType->getBusinessIdField()] = $key;
                    }
                }
            }
        }

        return \array_values($ouuids);
    }

    public function getDataLink(string $contentTypesCommaList, string $businessId): string
    {
        return $this->getDataLinks($contentTypesCommaList, [$businessId])[0] ?? $businessId;
    }

    public function hitFromBusinessIdToDataLink(ContentType $contentType, string $ouuid, array $rawData): Document
    {
        $revision = $this->getEmptyRevision($contentType, null);
        $revision->setRawData($rawData);
        $revision->setOuuid($ouuid);
        $revisionType = $this->formFactory->create(RevisionType::class, $revision, ['migration' => true, 'raw_data' => $revision->getRawData(), 'with_warning' => false]);
        $result = $this->walkRecursive($revisionType->get('data'), $rawData, function (string $name, $data, DataFieldType $dataFieldType, DataField $dataField) {
            if (null !== $data && (!\is_array($data) || \count($data) > 0)) {
                if ($dataFieldType->isVirtual()) {
                    return $data;
                }

                if (!$dataFieldType instanceof DataLinkFieldType) {
                    return [$name => $data];
                }

                $typesList = $dataField->getFieldType()->getDisplayOption('type');
                if (null == $typesList) {
                    return [$name => $data];
                }

                if (\is_string($data)) {
                    return [$name => $this->getDataLink($typesList, $data)];
                }

                return [$name => $this->getDataLinks($typesList, $data)];
            }

            return [];
        });
        unset($revisionType);

        return new Document($contentType->getName(), $ouuid, $result);
    }

    public function lockAllRevisions(\DateTime $until, string $by): int
    {
        try {
            return $this->revRepository->lockAllRevisions($until, $by);
        } catch (LockedException $e) {
            $this->logger->error('service.data.lock_revisions_error', [
                EmsFields::LOG_USERNAME_FIELD => $by,
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);
        }

        return 0;
    }

    public function lockRevisions(ContentType $contentType, \DateTime $until, string $by): int
    {
        try {
            return $this->revRepository->lockRevisions($contentType, $until, $by, true);
        } catch (LockedException $e) {
            $this->logger->error('service.data.lock_revisions_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_USERNAME_FIELD => $by,
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);
        }

        return 0;
    }

    public function unlockAllRevisions(string $by): int
    {
        try {
            return $this->revRepository->unlockAllRevisions($by);
        } catch (LockedException $e) {
            $this->logger->error('service.data.unlock_revisions_error', [
                EmsFields::LOG_USERNAME_FIELD => $by,
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);
        }

        return 0;
    }

    public function unlockRevisions(ContentType $contentType, string $by): int
    {
        try {
            return $this->revRepository->unlockRevisions($contentType, $by);
        } catch (LockedException $e) {
            $this->logger->error('service.data.unlock_revisions_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_USERNAME_FIELD => $by,
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);
        }

        return 0;
    }

    public function getAllDrafts(): array
    {
        return $this->revRepository->findAllDrafts();
    }
}
