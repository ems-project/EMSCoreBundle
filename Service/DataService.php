<?php

namespace EMS\CoreBundle\Service;

use DateInterval;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\QueryBuilder;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use EMS\CommonBundle\Common\Document;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Helper\ArrayTool;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Notification;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Event\RevisionFinalizeDraftEvent;
use EMS\CoreBundle\Event\RevisionNewDraftEvent;
use EMS\CoreBundle\Event\UpdateRevisionReferersEvent;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Exception\DataStateException;
use EMS\CoreBundle\Exception\DuplicateOuuidException;
use EMS\CoreBundle\Exception\HasNotCircleException;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\PrivilegeException;
use EMS\CoreBundle\Form\DataField\CollectionFieldType;
use EMS\CoreBundle\Form\DataField\ComputedFieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\DataLinkFieldType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Twig\AppExtension;
use Exception;
use IteratorAggregate;
use Monolog\Logger;
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
use Twig_Error;

/**
 * @todo Move Revision related logic to RevisionService
 */
class DataService
{
    const ALGO = OPENSSL_ALGO_SHA1;
    protected const SCROLL_TIMEOUT = '1m';

    /** @var resource|false|null */
    private $private_key;
    /** @var string|null */
    private $public_key;
    /** @var string */
    protected $lockTime;
    /** @var string */
    protected $instanceId;

    //TODO: service should be stateless
    /** @var array */
    private $cacheBusinessKey = [];
    //TODO: service should be stateless
    /** @var array */
    private $cacheOuuids = [];

    /** @var Twig_Environment */
    protected $twig;
    /** @var Registry */
    protected $doctrine;
    /** @var AuthorizationCheckerInterface*/
    protected $authorizationChecker;
    /** @var TokenStorageInterface */
    protected $tokenStorage;
    /** @Client $client*/
    protected $client;
    /** @var Mapping $mapping */
    protected $mapping;
    /** @var ObjectManager */
    protected $em;
    /** @var RevisionRepository */
    protected $revRepository;
    /** @var Session $session */
    protected $session;
    /** @var FormFactoryInterface */
    protected $formFactory;
    /** @var Container  */
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
    /** @var Logger */
    protected $logger;
    /** @var StorageManager */
    private $storageManager;
    /** @var EnvironmentService */
    private $environmentService;

    public function __construct(
        Registry $doctrine,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        string $lockTime,
        Client $client,
        Mapping $mapping,
        string $instanceId,
        Session $session,
        FormFactoryInterface $formFactory,
        Container $container,
        FormRegistryInterface $formRegistry,
        $dispatcher,
        ContentTypeService $contentTypeService,
        string $privateKey,
        Logger $logger,
        StorageManager $storageManager,
        Twig_Environment $twig,
        AppExtension $appExtension,
        UserService $userService,
        RevisionRepository $revisionRepository,
        EnvironmentService $environmentService
    ) {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->lockTime = $lockTime;
        $this->client = $client;
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

        $this->public_key = null;
        $this->private_key = null;

        if (! empty($privateKey)) {
            try {
                $this->private_key = openssl_pkey_get_private(file_get_contents($privateKey));
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
        if ($revision->getLockBy() === $lockerUsername && $revision->getLockUntil() > (new DateTime())) {
            $this->revRepository->unlockRevision($revision->getId());
        }
    }


    /**
     * @param Revision $revision
     * @param Environment $publishEnv
     * @param bool $super
     * @param string|null $username
     * @throws LockedException
     * @throws PrivilegeException
     * @throws Exception
     */
    public function lockRevision(Revision $revision, Environment $publishEnv = null, $super = false, $username = null)
    {

        if (!empty($publishEnv) && !$this->authorizationChecker->isGranted($revision->getContentType()->getPublishRole() ?: 'ROLE_PUBLISHER')) {
            throw new PrivilegeException($revision, 'You don\'t have publisher role for this content');
        }
        if (!empty($publishEnv) && is_object($publishEnv) && !empty($publishEnv->getCircles()) && !$this->authorizationChecker->isGranted('ROLE_ADMIN') && !$this->appTwig->inMyCircles($publishEnv->getCircles())) {
            throw new PrivilegeException($revision, 'You don\'t share any circle with this content');
        }
        if (empty($publishEnv) && !empty($revision->getContentType()->getCirclesField()) && !empty($revision->getRawData()[$revision->getContentType()->getCirclesField()])) {
            if (!$this->appTwig->inMyCircles($revision->getRawData()[$revision->getContentType()->getCirclesField()])) {
                throw new PrivilegeException($revision);
            }
        }

        /**@var Notification $notification*/
        foreach ($revision->getNotifications() as $notification) {
            if ($notification->getStatus() === Notification::PENDING && !$this->authorizationChecker->isGranted($notification->getTemplate()->getRole())) {
                throw new PrivilegeException($revision, 'A pending "' . $notification->getTemplate()->getName() . '" notification is locking this content');
            }
        }

        $em = $this->doctrine->getManager();
        if ($username === null) {
            $lockerUsername = $this->tokenStorage->getToken()->getUsername();
        } else {
            $lockerUsername = $username;
        }
        $now = new DateTime();
        if ($revision->getLockBy() != $lockerUsername && $now <  $revision->getLockUntil()) {
            throw new LockedException($revision);
        }

        if (!$username && !$this->container->get('app.twig_extension')->oneGranted($revision->getContentType()->getFieldType()->getFieldsRoles(), $super)) {
            throw new PrivilegeException($revision);
        }
        //TODO: test circles


        $this->revRepository->lockRevision($revision->getId(), $lockerUsername, new DateTime($this->lockTime));

        $revision->setLockBy($lockerUsername);
        if ($username) {
            //lock by a console script
            $revision->setLockUntil(new DateTime("+30 seconds"));
        } else {
            $revision->setLockUntil(new DateTime($this->lockTime));
        }

        $em->flush();
    }

    public function getAllDeleted(ContentType $contentType)
    {
        return $this->revRepository->findBy([
            'deleted' => true,
            'contentType' => $contentType,
            'endTime' => null,
        ], [
            'modified' => 'asc'
        ]);
    }

    public function getDataCircles(Revision $revision)
    {
        $out = [];
        if ($revision->getContentType()->getCirclesField()) {
            $fieldValue = $revision->getRawData()[$revision->getContentType()->getCirclesField()];
            if (!empty($fieldValue)) {
                if (is_array($fieldValue)) {
                    return $fieldValue;
                } else {
                    $out[] = $fieldValue;
                }
            }
        }
        return $out;
    }

    /**
     *
     * @param string $ouuid
     * @param ContentType $contentType
     * @param Environment $environment
     * @return Revision
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getRevisionByEnvironment($ouuid, ContentType $contentType, Environment $environment)
    {
        return $this->revRepository->findByEnvironment($ouuid, $contentType, $environment);
    }

    /**
     * @param FormInterface $form
     * @param array $objectArray
     * @param ContentType $contentType
     * @param string $type
     * @param string $ouuid|null
     * @param bool $migration
     * @return bool
     * @throws Throwable
     */
    public function propagateDataToComputedField(FormInterface $form, array& $objectArray, ContentType $contentType, string $type, ?string $ouuid, bool $migration = false, bool $finalize = true)
    {
        return $this->propagateDataToComputedFieldRecursive($form, $objectArray, $contentType, $type, $ouuid, $migration, $finalize, $objectArray, '');
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
            if ($contentType instanceof ContentType && $contentType->getBusinessIdField() && count($ouuids) > 0) {
                $result = $this->client->search([
                    'index' => $contentType->getEnvironment()->getAlias(),
                    'body' => [
                        'size' => sizeof($ouuids),
                        '_source' => $contentType->getBusinessIdField(),
                        'query' => [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            '_contenttype' => $contentType->getName()
                                        ]
                                    ],
                                    [
                                        'terms' => [
                                            '_id' => $ouuids
                                        ]
                                    ],
                                ]
                            ]
                        ]

                    ],
                    'size' => 100,
                    "scroll" => self::SCROLL_TIMEOUT
                ]);

                while (count($result['hits']['hits'] ?? []) > 0) {
                    foreach ($result['hits']['hits'] as $hits) {
                        $dataLink = $contentType->getName() . ':' . $hits['_id'];
                        $businessKeys[$dataLink] = $hits['_source'][$contentType->getBusinessIdField()] ?? $hits['_id'];
                        $this->cacheBusinessKey[$dataLink] = $businessKeys[$dataLink];
                    }
                    $result = $this->client->scroll([
                        'scroll_id' => $result['_scroll_id'],
                        'scroll' =>  self::SCROLL_TIMEOUT,
                    ]);
                }
            }
        }
        return array_values($businessKeys);
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
            if ($data !== null) {
                if ($dataFieldType->isVirtual()) {
                    return $data;
                }

                if ($dataFieldType instanceof DataLinkFieldType) {
                    if (is_string($data)) {
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
                /**@var DataFieldType $childType */
                $childType = $child->getConfig()->getType()->getInnerType();
                if ($childType instanceof DataFieldType) {
                    $childData = $rawData;
                    if (!$childType->isVirtual()) {
                        $childData = $rawData[$child->getName()] ?? null;
                    }
                    $output = array_merge($output, $this->walkRecursive($child, $childData, $callback));
                }
            }
        }
        return $callback($form->getName(), $output, $dataFieldType, $dataField);
    }

    /**
     * @param FormInterface $form
     * @param array $objectArray
     * @param ContentType $contentType
     * @param string $type
     * @param string $ouuid|null
     * @param bool $migration
     * @param array|null $parent
     * @param string $path
     * @return bool
     * @throws Throwable
     */
    private function propagateDataToComputedFieldRecursive(FormInterface $form, array& $objectArray, ContentType $contentType, string $type, ?string $ouuid, bool $migration, bool $finalize, ?array &$parent, string $path)
    {
        $found = false;
        /** @var DataField $dataField*/
        $dataField = $form->getNormData();

        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $form->getConfig()->getType()->getInnerType();

        $options = $dataField->getFieldType()->getOptions();

        if (!$dataFieldType::isVirtual(!$options ? [] : $options)) {
            $path .= ($path == '' ? '' : '.') . $form->getConfig()->getName();
        }

        $extraOption = $dataField->getFieldType()->getExtraOptions();
        if (isset($extraOption['postProcessing']) && !empty($extraOption['postProcessing'])) {
            try {
                $out = $this->twig->createTemplate($extraOption['postProcessing'])->render([
                    '_source' => $objectArray,
                    '_type' => $type,
                    '_id' => $ouuid,
                    'index' => $contentType->getEnvironment()->getAlias(),
                    'migration' => $migration,
                    'parent' => $parent,
                    'path' => $path,
                    'finalize' => $finalize,
                ]);
                $out = trim($out);

                if (strlen($out) > 0) {
                    $json = json_decode($out, true);
                    $meg = json_last_error_msg();
                    if (strcasecmp($meg, 'No error') == 0) {
                        $objectArray[$dataField->getFieldType()->getName()] = $json;
                        $found = true;
                    } else {
                        $this->logger->warning('service.data.json_parse_post_processing_error', [
                            'field_name' => $dataField->getFieldType()->getName(),
                            EmsFields::LOG_ERROR_MESSAGE_FIELD => $out,
                        ]);
                    }
                }
            } catch (Exception $e) {
                if ($e->getPrevious() && $e->getPrevious() instanceof CantBeFinalizedException) {
                    if (!$migration) {
                        $form->addError(new FormError($e->getPrevious()->getMessage()));
                        $this->logger->warning('service.data.cant_finalize_field', [
                            'field_name' => $dataField->getFieldType()->getName(),
                            'field_display' => isset($dataField->getFieldType()->getDisplayOptions()['label']) && !empty($dataField->getFieldType()->getDisplayOptions()['label']) ? $dataField->getFieldType()->getDisplayOptions()['label'] : $dataField->getFieldType()->getName(),
                            EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getPrevious()->getMessage(),
                        ]);
                    }
                } else {
                    $this->logger->warning('service.data.json_parse_post_processing_error', [
                        'field_name' => $dataField->getFieldType()->getName(),
                        EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                        EmsFields::LOG_EXCEPTION_FIELD => $e,
                    ]);
                }
            }
        }
        if ($form->getConfig()->getType()->getInnerType() instanceof ComputedFieldType) {
            $template = $dataField->getFieldType()->getDisplayOptions()['valueTemplate'] ?? '';

            $out = null;
            if (!empty($template)) {
                try {
                    $out = $this->twig->createTemplate($template)->render([
                        '_source' => $objectArray,
                        '_type' => $type,
                        '_id' => $ouuid,
                        'index' => $contentType->getEnvironment()->getAlias(),
                        'migration' => $migration,
                        'parent' => $parent,
                        'path' => $path,
                        'finalize' => $finalize,
                    ]);

                    if ($dataField->getFieldType()->getDisplayOptions()['json']) {
                        $out = json_decode($out, true);
                    } else {
                        $out = trim($out);
                    }
                } catch (Exception $e) {
                    if ($e->getPrevious() && $e->getPrevious() instanceof CantBeFinalizedException) {
                        $form->addError(new FormError($e->getPrevious()->getMessage()));
                    }

                    $this->logger->warning('service.data.template_parse_error', [
                        EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                        EmsFields::LOG_EXCEPTION_FIELD => $e,
                        'computed_field_name' => $dataField->getFieldType()->getName(),
                    ]);
                }
            }
            if ($out !== null && $out !== false && (!is_array($out) || !empty($out))) {
                $objectArray[$dataField->getFieldType()->getName()] = $out;
            } else if (isset($objectArray[$dataField->getFieldType()->getName()])) {
                unset($objectArray[$dataField->getFieldType()->getName()]);
            }
            $found = true;
        }

        if ($dataFieldType->isContainer() && $form instanceof IteratorAggregate) {
            /** @var FormInterface $child */
            foreach ($form->getIterator() as $child) {

               /**@var DataFieldType $childType */
                $childType = $child->getConfig()->getType()->getInnerType();

                if ($childType instanceof CollectionFieldType) {
                    $fieldName = $child->getNormData()->getFieldType()->getName();

                    foreach ($child->all() as $collectionChild) {
                        if (isset($objectArray[$fieldName])) {
                            foreach ($objectArray[$fieldName] as &$elementsArray) {
                                $found = $this->propagateDataToComputedFieldRecursive($collectionChild, $elementsArray, $contentType, $type, $ouuid, $migration, $finalize, $parent, $path . ($path == '' ? '' : '.') . $fieldName) || $found;
                            }
                        }
                    }
                } elseif ($childType instanceof DataFieldType) {
                    $found = $this->propagateDataToComputedFieldRecursive($child, $objectArray, $contentType, $type, $ouuid, $migration, $finalize, $parent, $path) || $found;
                }
            }
        }
        return $found;
    }

    public function convertInputValues(DataField $dataField)
    {
        foreach ($dataField->getChildren() as $child) {
            $this->convertInputValues($child);
        }
        if (!empty($dataField->getFieldType()) && !empty($dataField->getFieldType()->getType())) {
                /**@var DataFieldType $dataFieldType*/
            $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
            if ($dataFieldType instanceof DataFieldType) {
                $dataFieldType->convertInput($dataField);
            } else if (! DataService::isInternalField($dataField->getFieldType()->getName())) {
                $this->logger->warning('service.data.not_a_data_field', [
                    'field_name' => $dataField->getFieldType()->getName()
                ]);
            }
        }
    }

    public static function isInternalField(string $fieldName)
    {
        return in_array($fieldName, ['_ems_internal_deleted', 'remove_collection_item']);
    }

    public function generateInputValues(DataField $dataField)
    {

        foreach ($dataField->getChildren() as $child) {
            $this->generateInputValues($child);
        }
        if (!empty($dataField->getFieldType()) && !empty($dataField->getFieldType()->getType())) {
            $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
            if ($dataFieldType instanceof  DataFieldType) {
                $dataFieldType->generateInput($dataField);
            } else if (! DataService::isInternalField($dataField->getFieldType()->getName())) {
                $this->logger->warning('service.data.not_a_data_field', [
                    'field_name' => $dataField->getFieldType()->getName()
                ]);
            }
        }
    }

    /**
     * @param string $ouuid
     * @param array $rawdata
     * @param ContentType $contentType
     * @param bool $byARealUser
     * @return Revision
     * @throws Exception
     */
    public function createData($ouuid, array $rawdata, ContentType $contentType, $byARealUser = true)
    {

        $now = new DateTime();
        $until = $now->add(new DateInterval($byARealUser ? "PT5M" : "PT1M"));//+5 minutes
        $newRevision = new Revision();
        $newRevision->setContentType($contentType);
        $newRevision->setOuuid($ouuid);
        $newRevision->setStartTime($now);
        $newRevision->setEndTime(null);
        $newRevision->setDeleted(false);
        $newRevision->setDraft(true);
        if ($byARealUser) {
            $newRevision->setLockBy($this->tokenStorage->getToken()->getUsername());
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
                    'endTime' => null
            ]);
            
            if (!empty($anotherObject)) {
                throw new ConflictHttpException('Duplicate OUUID ' . $ouuid . ' for this content type');
            }
        }
        
        $em->persist($newRevision);
        $em->flush();
        return $newRevision;
    }

    /**
     * @deprecated
     * @param array $array
     * @param int $sort_flags
     */
    public static function ksortRecursive(&$array, $sort_flags = SORT_REGULAR)
    {
        @trigger_error("DataService::ksortRecursive is deprecated use the ArrayTool::normalizeArray instead", E_USER_DEPRECATED);

        ArrayTool::normalizeArray($array, $sort_flags);
    }
    
    public function sign(Revision $revision, $silentPublish = false)
    {
        if ($silentPublish && $revision->getAutoSave()) {
            $objectArray = $revision->getAutoSave();
        } else {
            $objectArray = $revision->getRawData();
        }

        $objectArray[Mapping::CONTENT_TYPE_FIELD] = $revision->getContentType()->getName();
        if (isset($objectArray[Mapping::HASH_FIELD])) {
            unset($objectArray[Mapping::HASH_FIELD]);
        }
        if (isset($objectArray[Mapping::SIGNATURE_FIELD])) {
            unset($objectArray[Mapping::SIGNATURE_FIELD]);
        }
        ArrayTool::normalizeArray($objectArray);
        $json = json_encode($objectArray);

        $revision->setSha1($this->storageManager->computeStringHash($json));
        $objectArray[Mapping::HASH_FIELD] = $revision->getSha1();

        if (!$silentPublish && $this->private_key) {
            $signature = null;
            if (openssl_sign($json, $signature, $this->private_key, OPENSSL_ALGO_SHA1)) {
                $objectArray[Mapping::SIGNATURE_FIELD] = base64_encode($signature);
            } else {
                $this->logger->warning('service.data.not_able_to_sign', [
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => openssl_error_string(),
                ]);
            }
        }

        $revision->setRawData($objectArray);

        return $objectArray;
    }

    public function getPublicKey()
    {
        if ($this->private_key && empty($this->public_key)) {
            $certificate = openssl_pkey_get_private($this->private_key);
            $details = openssl_pkey_get_details($certificate);
            $this->public_key = $details['key'];
        }
        return $this->public_key;
    }

    public function getCertificateInfo()
    {
        if ($this->private_key) {
            $certificate = openssl_pkey_get_private($this->private_key);
            $details = openssl_pkey_get_details($certificate);
            return $details;
        }
        return null;
    }

    public function testIntegrityInIndexes(Revision $revision)
    {
        $this->sign($revision);

        //test integrity
        foreach ($revision->getEnvironments() as $environment) {
            try {
                $indexedItem = $this->client->get([
                        '_source_exclude' => ['*.attachment', '*._attachment'],
                        'id' => $revision->getOuuid(),
                        'type' => $revision->getContentType()->getName(),
                        'index' => $this->contentTypeService->getIndex($revision->getContentType(), $environment),
                ])['_source'];

                ArrayTool::normalizeArray($indexedItem);

                if (isset($indexedItem[Mapping::PUBLISHED_DATETIME_FIELD])) {
                    unset($indexedItem[Mapping::PUBLISHED_DATETIME_FIELD]);
                }

                if (isset($indexedItem[Mapping::HASH_FIELD])) {
                    if ($indexedItem[Mapping::HASH_FIELD] != $revision->getSha1()) {
                        $this->logger->warning('service.data.hash_mismatch', [
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            'index_hash' => $indexedItem[Mapping::HASH_FIELD],
                            'db_hash' => $revision->getSha1(),
                        ]);
                    }
                    unset($indexedItem[Mapping::HASH_FIELD]);

                    if (isset($indexedItem[Mapping::SIGNATURE_FIELD])) {
                        $binary_signature = base64_decode($indexedItem[Mapping::SIGNATURE_FIELD]);
                        unset($indexedItem[Mapping::SIGNATURE_FIELD]);
                        $data = json_encode($indexedItem);

                        // Check signature
                        $ok = openssl_verify($data, $binary_signature, $this->getPublicKey(), self::ALGO);
                        if ($ok === 0) {
                            $this->logger->warning('service.data.check_signature_failed', [
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            ]);
                        } elseif ($ok !== 1) { //1 means signature is ok
                            $this->logger->warning('service.data.error_check_signature', [
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                                EmsFields::LOG_ERROR_MESSAGE_FIELD => openssl_error_string(),
                                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                            ]);
                        }
                    } else {
                        $data = json_encode($indexedItem);
                        if ($this->private_key) {
                            $this->logger->warning('service.data.revision_not_signed', [
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                            ]);
                        }
                    }

                    $computedHash = $this->storageManager->computeStringHash($data) ;
                    if ($computedHash !== $revision->getSha1()) {
                        $this->logger->warning('service.data.computed_hash_mismatch', [
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            'computed_hash' => $computedHash,
                            'db_hash' => $revision->getSha1(),
                        ]);
                    }
                } else {
                    $this->logger->warning('service.data.hash_missing', [
                        EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                        EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    ]);
                }
            } catch (Exception $e) {
                $this->logger->error('service.data.integrity_failed', [
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                ]);
            }
        }
    }

    /**
     * @param Revision $revision
     * @return FormInterface
     * @throws Exception
     */
    public function buildForm(Revision $revision)
    {
        if ($revision->getDatafield() == null) {
            $this->loadDataStructure($revision);
        }

        //Get the form from Factory
        $builder = $this->formFactory->createBuilder(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);
        $form = $builder->getForm();
        return $form;
    }

    /**
     * Try to finalize a revision
     *
     * @param Revision $revision
     * @param FormInterface $form
     * @param string $username
     * @param boolean $computeFields (allow to sky computedFields compute, i.e during a post-finalize)
     * @return Revision
     * @throws DataStateException
     * @throws Exception
     * @throws Throwable
     */
    public function finalizeDraft(Revision $revision, FormInterface &$form = null, $username = null, $computeFields = true)
    {
        if ($revision->getDeleted()) {
            throw new Exception("Can not finalized a deleted revision");
        }
        if (null == $form && empty($username)) {
            if ($revision->getDatafield() == null) {
                $this->loadDataStructure($revision);
            }

            //Get the form from Factory
            $builder = $this->formFactory->createBuilder(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);
            $form = $builder->getForm();
        }

        if (empty($username)) {
            $username = $this->tokenStorage->getToken()->getUsername();
        }
        $this->lockRevision($revision, null, false, $username);


        $em = $this->doctrine->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');

        //TODO: test if draft and last version publish in

        if (!empty($revision->getAutoSave())) {
            throw new DataStateException('An auto save is pending, it can not be finalized.');
        }

        $objectArray = $revision->getRawData();

        $this->updateDataStructure($revision->getContentType()->getFieldType(), $form->get('data')->getNormData());
        try {
            if ($computeFields && $this->propagateDataToComputedField($form->get('data'), $objectArray, $revision->getContentType(), $revision->getContentType()->getName(), $revision->getOuuid())) {
                $revision->setRawData($objectArray);
            }
        } catch (CantBeFinalizedException $e) {
            $form->addError(new FormError($e->getMessage()));
        }

        $previousObjectArray = null;

        $revision->setRawDataFinalizedBy($username);

        $objectArray = $this->sign($revision);

        if (empty($form) || $this->isValid($form, $revision->getContentType()->getParentField(), $objectArray)) {
            $objectArray[Mapping::PUBLISHED_DATETIME_FIELD] = (new DateTime())->format(DateTime::ISO8601);

            $config = [
              'index' => $this->contentTypeService->getIndex($revision->getContentType()),
              'type' => $revision->getContentType()->getName(),
              'body' => $objectArray,
            ];

            if ($revision->getContentType()->getHavePipelines()) {
                $config['pipeline'] = $this->instanceId . $revision->getContentType()->getName();
            }

            if (empty($revision->getOuuid())) {
                $status = $this->client->index($config);
                $revision->setOuuid($status['_id']);
            } else {
                $config['id'] = $revision->getOuuid();
                $this->client->index($config);

                $item = $repository->findByOuuidContentTypeAndEnvironment($revision);
                if ($item) {
                    $this->lockRevision($item, null, false, $username);
                    $previousObjectArray = $item->getRawData();
                    $item->removeEnvironment($revision->getContentType()->getEnvironment());
                    $em->persist($item);
                    $this->unlockRevision($item, $username);
                }
            }

            $revision->addEnvironment($revision->getContentType()->getEnvironment());
            $revision->setDraft(false);

            $revision->setFinalizedBy($username);

            $em->persist($revision);
            $em->flush();


            $this->unlockRevision($revision, $username);
            $this->dispatcher->dispatch(RevisionFinalizeDraftEvent::NAME, new RevisionFinalizeDraftEvent($revision));


            $this->logger->notice('log.data.revision.finalized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $revision->getContentType()->getEnvironment()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            ]);

            try {
                $this->postFinalizeTreatment($revision->getContentType()->getName(), $revision->getOuuid(), $form->get('data'), $previousObjectArray);
            } catch (Exception $e) {
                $this->logger->warning('service.data.post_finalize_failed', [
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_ENVIRONMENT_FIELD => $revision->getContentType()->getEnvironment()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
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
            while ($fieldForm !== null && !$fieldForm->getNormData() instanceof DataField) {
                $fieldForm = $fieldForm->getOrigin()->getParent();
            }

            if (!$fieldForm->getNormData() instanceof DataField) {
                continue;
            }
            /** @var DataField $dataField */
            $dataField = $fieldForm->getNormData();
            if (empty($dataField->getMessages())) {
                continue;
            }
            if (sizeof($dataField->getMessages()) === 1) {
                $errorMessage = $dataField->getMessages()[0];
            } else {
                $errorMessage = sprintf('["%s"]', \implode('","', $dataField->getMessages()));
            }

            $fieldName = $fieldForm->getNormData()->getFieldType()->getDisplayOption('label', $fieldForm->getNormData()->getFieldType()->getName());
            $errorPath = '';

            $parent = $fieldForm;
            while (($parent = $parent->getParent()) !== null) {
                if ($parent->getNormData() instanceof DataField && $parent->getNormData()->getFieldType()->getParent() !== null) {
                    $errorPath .= $parent->getNormData()->getFieldType()->getDisplayOption('label', $parent->getNormData()->getFieldType()->getName()) . ' > ';
                }
            }
            $errorPath .= $fieldName;

            $this->logger->warning('service.data.error_with_fields', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $errorMessage,
                EmsFields::LOG_FIELD_IN_ERROR_FIELD => $fieldName,
                EmsFields::LOG_PATH_IN_ERROR_FIELD => $errorPath,
            ]);
        }

        $this->logger->warning('service.data.cant_be_finalized', [
            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
            EmsFields::LOG_ENVIRONMENT_FIELD => $revision->getContentType()->getEnvironment()->getName(),
            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
        ]);
    }


    /**
     * Parcours all fields and call DataFieldsType postFinalizeTreament function
     *
     * @param string $type
     * @param string $id
     * @param FormInterface $form
     * @param array|null $previousObjectArray
     */
    public function postFinalizeTreatment($type, $id, FormInterface $form, $previousObjectArray)
    {
        /** @var FormInterface $subForm */
        foreach ($form->all() as $subForm) {
            if ($subForm->getNormData() instanceof DataField) {
                /** @var DataFieldType $dataFieldType */
                $dataFieldType = $subForm->getConfig()->getType()->getInnerType();
                $childrenPreviousData = $dataFieldType->postFinalizeTreatment($type, $id, $subForm->getNormData(), $previousObjectArray);
                $this->postFinalizeTreatment($type, $id, $subForm, $childrenPreviousData);
            }
        }
    }

    /**
     *
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

        if (count($contentTypes) != 1) {
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

        if (count($revisions) == 1) {
            if ($revisions[0] instanceof Revision && null == $revisions[0]->getEndTime()) {
                return $revisions[0];
            } else {
                throw new NotFoundHttpException('Revision for ouuid ' . $ouuid . ' and contenttype ' . $type . ' with end time ' . $revisions[0]->getEndTime());
            }
        } elseif (count($revisions) == 0) {
            throw new NotFoundHttpException('Revision not found for ouuid ' . $ouuid . ' and contenttype ' . $type);
        } else {
            throw new Exception('Too much newest revisions available for ouuid ' . $ouuid . ' and contenttype ' . $type);
        }
    }

    /**
     * @param ContentType $contentType
     * @param string|null $ouuid
     * @param array|null $rawData
     * @return Revision
     * @throws DuplicateOuuidException
     * @throws HasNotCircleException
     * @throws Throwable
     */
    public function newDocument(ContentType $contentType, ?string $ouuid = null, ?array $rawData = null)
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
                ]);
                $raw = json_decode($defaultValue, true);
                if ($raw === null) {
                    $this->logger->error('service.data.default_value_error', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                        EmsFields::LOG_OUUID_FIELD => $ouuid,
                    ]);
                } else {
                    $revision->setRawData($raw);
                }
            } catch (Twig_Error $e) {
                $this->logger->error('service.data.default_value_template_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    EmsFields::LOG_OUUID_FIELD => $ouuid,
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                ]);
            }
        }

        if ($rawData) {
            $rawData = array_diff_key($rawData, Mapping::MAPPING_INTERNAL_FIELDS);

            if ($revision->getRawData()) {
                $revision->setRawData(array_replace_recursive($rawData, $revision->getRawData()));
            } else {
                $revision->setRawData($rawData);
            }
        }


        $now = new DateTime('now');
        $revision->setContentType($contentType);
        $revision->setDraft(true);
        $revision->setOuuid($ouuid);
        $revision->setDeleted(false);
        $revision->setStartTime($now);
        $revision->setEndTime(null);
        $revision->setLockBy($this->userService->getCurrentUser()->getUsername());
        $revision->setLockUntil(new DateTime($this->lockTime));

        if ($contentType->getCirclesField()) {
            $fieldType = $contentType->getFieldType()->getChildByPath($contentType->getCirclesField());
            if ($fieldType) {
                /**@var User $user */
                $user = $this->userService->getCurrentUser();
                $options = $fieldType->getDisplayOptions();
                if (isset($options['multiple']) && $options['multiple']) {
                    //merge all my circles with the default value
                    $circles = [];
                    if (isset($options['defaultValue'])) {
                        $circles = json_decode($options['defaultValue']);
                        if (!is_array($circles)) {
                            $circles = [$circles];
                        }
                    }
                    $circles = array_merge($circles, $user->getCircles());
                    $revision->setRawData([$contentType->getCirclesField() => $circles]);
                    $revision->setCircles($circles);
                } else {
                    //set first of my circles
                    if (!empty($user->getCircles())) {
                        $revision->setRawData([$contentType->getCirclesField() => $user->getCircles()[0]]);
                        $revision->setCircles([$user->getCircles()[0]]);
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
     * @param ContentType $contentType
     * @throws HasNotCircleException
     */
    public function hasCreateRights(ContentType $contentType)
    {

        $userCircles = $this->userService->getCurrentUser()->getCircles();
        $environment = $contentType->getEnvironment();
        $environmentCircles = $environment->getCircles();
        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN') && !empty($environmentCircles)) {
            if (empty($userCircles)) {
                throw new HasNotCircleException($environment);
            }
            $found = false;
            foreach ($userCircles as $userCircle) {
                if (in_array($userCircle, $environmentCircles)) {
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
    }

    private function setCircles(Revision $revision)
    {
        $objectArray = $revision->getRawData();
        if (!empty($revision->getContentType()->getCirclesField()) && isset($objectArray[$revision->getContentType()->getCirclesField()])  && !empty($objectArray[$revision->getContentType()->getCirclesField()])) {
            $revision->setCircles(is_array($objectArray[$revision->getContentType()->getCirclesField()]) ? $objectArray[$revision->getContentType()->getCirclesField()] : [$objectArray[$revision->getContentType()->getCirclesField()]]);
        } else {
            $revision->setCircles(null);
        }
    }

    private function setLabelField(Revision $revision)
    {
        $objectArray = $revision->getRawData();
        $labelField = $revision->getContentType()->getLabelField();
        if (!empty($labelField) &&
                isset($objectArray[$labelField])  &&
                !empty($objectArray[$labelField]) ) {
            $revision->setLabelField($objectArray[$labelField]);
        } else {
            $revision->setLabelField(null);
        }
    }

    /**
     * @param string $type
     * @param string $ouuid
     * @param Revision|null $fromRev
     * @param string|null $username
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

        if ($contentType === null) {
            throw new NotFoundHttpException('ContentType ' . $type . ' Not found');
        }


        $revision = $this->getNewestRevision($type, $ouuid);
        $revision->setDeleted(false);
        if (null !== $revision->getDataField()) {
            $revision->getDataField()->propagateOuuid($revision->getOuuid());
        }


         $this->setMetaFields($revision);

        $this->lockRevision($revision, null, false, $username);



        if (! $revision->getDraft()) {
            $now = new DateTime();

            if ($fromRev) {
                $newDraft = new Revision($fromRev);
            } else {
                $newDraft = new Revision($revision);
            }

            $newDraft->setStartTime($now);
            $revision->setEndTime($now);

            $this->lockRevision($newDraft, null, false, $username);

            $em->persist($revision);
            $em->persist($newDraft);
            $em->flush();

            $this->dispatcher->dispatch(RevisionNewDraftEvent::NAME, new RevisionNewDraftEvent($newDraft));

            return $newDraft;
        }
        return $revision;
    }

    /**
     * @param Revision $revision
     * @return bool|int
     * @throws LockedException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws PrivilegeException
     */
    public function discardDraft(Revision $revision, $super = false, $username = null)
    {
        $this->lockRevision($revision, null, $super, $username);

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');

        if (!$revision->getDraft() || null != $revision->getEndTime()) {
            throw new BadRequestHttpException('Only authorized on a draft');
        }

        $hasPreviousRevision = false;

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

            if (count($result) == 1) {
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
        return $hasPreviousRevision;
    }

    /**
     * @param string $type
     * @param string $ouuid
     * @throws LockedException
     * @throws Missing404Exception
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws PrivilegeException
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
        if (!$contentTypes || count($contentTypes) != 1) {
            throw new NotFoundHttpException('Content Type not found');
        }

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');


        $revisions = $repository->findBy([
                'ouuid' => $ouuid,
                'contentType' => $contentTypes[0]
        ]);

        /** @var Revision $revision */
        foreach ($revisions as $revision) {
            $this->lockRevision($revision);

            /** @var Environment $environment */
            foreach ($revision->getEnvironments() as $environment) {
                try {
                    $this->client->delete([
                        'index' => $this->contentTypeService->getIndex($revision->getContentType()),
                        'type' => $revision->getContentType()->getName(),
                        'id' => $revision->getOuuid(),
                        'refresh' => true,
                    ]);
                    $this->logger->notice('service.data.unpublished', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                        EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
                        EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                    ]);
                } catch (Missing404Exception $e) {
                    if (!$revision->getDeleted()) {
                        $this->logger->warning('service.data.already_unpublished', [
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
                            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                        ]);
                    }
                    throw $e;
                }
                $revision->removeEnvironment($environment);
            }
            $revision->setDeleted(true);
            $revision->setDeletedBy($this->tokenStorage->getToken()->getUsername());
            $em->persist($revision);
        }
        $this->logger->notice('service.data.deleted', [
            EmsFields::LOG_CONTENTTYPE_FIELD => $type,
            EmsFields::LOG_OUUID_FIELD => $ouuid,
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
        ]);
        $em->flush();
    }

    /**
     * @param ContentType $contentType
     * @param string $ouuid
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
     * @param ContentType $contentType
     * @param string $ouuid
     * @return int|null
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
            if ($revision->getEndTime() === null) {
                $revision->setDraft(true);
                $out = $revision->getId();
            }
            $em->persist($revision);
        }
        $em->flush();
        return $out;
    }


    /**
     * @param FieldType $meta
     * @param DataField $dataField
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
            } else if (! DataService::isInternalField($dataField->getFieldType()->getName())) {
                $this->logger->warning('service.data.not_a_data_field', [
                    'field_name' => $dataField->getFieldType()->getName()
                ]);
            }
        }

        if ($isContainer) {
            /** @var FieldType $field */
            foreach ($meta->getChildren() as $field) {
                //no need to generate the structure for delete field
                if (!$field->getDeleted()) {
                    $child = $dataField->__get('ems_' . $field->getName());
                    if (null == $child) {
                        $child = new DataField();
                        $child->setFieldType($field);
                        $child->setOrderKey($field->getOrderKey());
                        $child->setParent($dataField);
                        $dataField->addChild($child);
                        if (isset($field->getDisplayOptions()['defaultValue'])) {
                            $child->setEncodedText($field->getDisplayOptions()['defaultValue']);
                        }
                    }
                    if (strcmp($field->getType(), CollectionFieldType::class) != 0) {
                        $this->updateDataStructure($field, $child);
                    }
                }
            }
        }
    }

    /**
     * Assign data in dataValues based on the elastic index content
     *
     * @param DataField $dataField
     * @param array $elasticIndexDatas
     * @param bool $isMigration
     *
     */
    public function updateDataValue(DataField $dataField, array &$elasticIndexDatas, $isMigration = false)
    {
        $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
        if ($dataFieldType instanceof DataFieldType) {
            $fieldName = $dataFieldType->getJsonName($dataField->getFieldType());
            if (null === $fieldName) {//Virtual container
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
                } else if (array_key_exists($fieldName, $elasticIndexDatas)) {
                    $treatedFields = $dataFieldType->importData($dataField, $elasticIndexDatas[$fieldName], $isMigration);
                    foreach ($treatedFields as $fieldName) {
                        unset($elasticIndexDatas[$fieldName]);
                    }
                }
            }
        } else if (! DataService::isInternalField($dataField->getFieldType()->getName())) {
            $this->logger->warning('service.data.not_a_data_field', [
                'field_name' => $dataField->getFieldType()->getName()
            ]);
        }
    }

    /**
     * @param Revision $revision
     * @throws Exception
     */
    public function loadDataStructure(Revision $revision)
    {
        $data = new DataField();
        $data->setFieldType($revision->getContentType()->getFieldType());
        $data->setOrderKey($revision->getContentType()->getFieldType()->getOrderKey());
        $data->setRawData($revision->getRawData());
        $revision->setDataField($data);
        $this->updateDataStructure($revision->getContentType()->getFieldType(), $revision->getDataField());
        //$revision->getDataField()->updateDataStructure($this->formRegistry, $revision->getContentType()->getFieldType());
        $object = $revision->getRawData();
        $this->updateDataValue($data, $object);
        unset($object[Mapping::FINALIZED_BY_FIELD]);
        unset($object[Mapping::FINALIZATION_DATETIME_FIELD]);
        if (count($object) > 0) {
            $html = DataService::arrayToHtml($object);

            $this->logger->warning('service.data.data_not_consumed', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
                'count' => count($object),
                'data' => $html,
            ]);
        }
    }

    /**
     * @param Revision $revision
     * @return array
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
        $this->updateDataStructure($revision->getContentType()->getFieldType(), $form->get('data')->getNormData());
        $this->propagateDataToComputedField($form->get('data'), $objectArray, $revision->getContentType(), $revision->getContentType()->getName(), $revision->getOuuid());

        if ($finalizedBy !== false) {
            $objectArray[Mapping::FINALIZED_BY_FIELD] = $finalizedBy;
        }
        if ($finalizationDate !== false) {
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

        /**@var FormInterface $subForm*/
        foreach ($iteratedOn as $subForm) {
            if ($subForm->getConfig()->getCompound()) {
                $out[$subForm->getName()] = $this->getSubmitData($subForm);
            }
        }
        return $out;
    }

    /**
     * @param ContentType $contentType
     * @param string $user
     * @return Revision
     * @throws Exception
     */
    public function getEmptyRevision(ContentType $contentType, $user)
    {

        $now = new DateTime();
        $until = $now->add(new DateInterval("PT5M"));//+5 minutes
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
            $out .= '<li>' . $id . ':';
            if (is_array($item)) {
                $out .= DataService::arrayToHtml($item);
            } else {
                $out .= $item;
            }
            $out .= '</li>';
        }
        return $out . '</ul>';
    }

    /**
     * @param FormInterface $form
     * @param DataField|null $parent
     * @param null $masterRawData
     * @return bool
     * @throws Exception
     */
    public function isValid(FormInterface &$form, DataField $parent = null, &$masterRawData = null)
    {
        $viewData = $form->getNormData();

        if ($viewData instanceof Revision) {
            $topLevelDataFieldForm = $form->get('data');
            return $this->isValid($topLevelDataFieldForm, $parent, $masterRawData);
        }

        if (! $viewData instanceof DataField) {
            if (! DataService::isInternalField($form->getName())) {
                $this->logger->warning('service.data.not_a_data_field', [
                    'field_name' => $form->getName()
                ]);
            }
            return true;
        }

        $dataField = $viewData;

        $dataFieldType = null;
        if ($dataField->getFieldType() !== null && $dataField->getFieldType()->getType() !== null) {
            /** @var DataFieldType $dataFieldType */
            $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
            $dataFieldType->isValid($dataField, $parent, $masterRawData);
        }
        $isValid = true;
        if ($dataFieldType !== null && $dataFieldType->isContainer()) {//If dataField is container or type is null => Container => Recursive
            $formChildren = $form->all();
            foreach ($formChildren as $child) {
                if ($child instanceof FormInterface) {
                    $tempIsValid = $this->isValid($child, $dataField, $masterRawData);//Recursive
                    $isValid = $isValid && $tempIsValid;
                }
            }
            if (!$isValid) {
                $form->addError(new FormError("At least one field is not valid!"));
            }
        }
        if ($dataFieldType !== null && !$dataFieldType->isValid($dataField, $parent, $masterRawData)) {
            $isValid = false;
            $form->addError(new FormError("This Field is not valid! " . $dataField->getMessages()[0]));
        }

        if ($form->getErrors(true, true)->count() > 0) {
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * @param int $id
     * @param ContentType $type
     * @return Revision
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

        if (count($contentTypes) != 1) {
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

        if (count($revisions) == 1) {
            if ($revisions[0] instanceof Revision && null == $revisions[0]->getEndTime()) {
                $revision = $revisions[0];
                return $revision;
            } else {
                throw new Exception('Revision for ouuid ' . $id . ' and contenttype ' . $type . ' with end time ' . $revisions[0]->getEndTime());
            }
        } elseif (count($revisions) == 0) {
            throw new NotFoundHttpException('Revision not found for id ' . $id . ' and contenttype ' . $type);
        } else {
            throw new Exception('Too much newest revisions available for ouuid ' . $id . ' and contenttype ' . $type);
        }
    }

    /**
     * @param Revision $revision
     * @param array $rawData
     * @param string $replaceOrMerge
     * @return Revision
     * @throws LockedException
     * @throws PrivilegeException
     */
    public function replaceData(Revision $revision, array $rawData, $replaceOrMerge = "replace")
    {

        if (! $revision->getDraft()) {
            $em = $this->doctrine->getManager();
            $this->lockRevision($revision);

            $now = new DateTime();

            $newDraft = new Revision($revision);

            if ($replaceOrMerge === "replace") {
                $newDraft->setRawData($rawData);
            } elseif ($replaceOrMerge === "merge") {
                $newRawData = array_merge($revision->getRawData(), $rawData);
                $newDraft->setRawData($newRawData);
            } else {
                $this->logger->error('service.data.unknown_update_type', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
                    'update_type' => $replaceOrMerge,
                ]);
                return $revision;
            }

            $newDraft->setStartTime($now);
            $revision->setEndTime($now);

            $this->lockRevision($newDraft);

            $em->persist($revision);
            $em->persist($newDraft);
            $em->flush();
            return $newDraft;
        } else {
            $this->logger->error('service.data.not_a_draft', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
                'update_type' => $replaceOrMerge,
            ]);
        }
        return $revision;
    }

    public function waitForGreen()
    {
        $this->client->cluster()->health(['wait_for_status' => 'green']);
    }


    public function getDataFieldsStructure(FormInterface $form)
    {
        /**@var DataField $out*/
        $out = $form->getNormData();
        foreach ($form as $item) {
            if ($item->getNormData() instanceof DataField) {
                $out->addChild($item->getNormData());
                $this->getDataFieldsStructure($item);
            }
            //else shoudl be a sub-field
        }
        return $out;
    }

    /**
     * Call on UpdateRevisionReferersEvent. Will try to update referers objects
     * @param UpdateRevisionReferersEvent $event
     * @throws DataStateException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws PrivilegeException
     * @throws Throwable
     */
    public function updateReferers(UpdateRevisionReferersEvent $event)
    {

        $form = null;
        foreach ($event->getToCleanOuuids() as $ouuid) {
            $key = explode(':', $ouuid);
            try {
                $revision = $this->initNewDraft($key[0], $key[1]);
                $data = $revision->getRawData();
                if (empty($data[$event->getTargetField()])) {
                    $data[$event->getTargetField()] = [];
                }
                if (in_array($event->getRefererOuuid(), $data[$event->getTargetField()])) {
                    $data[$event->getTargetField()] = array_diff($data[$event->getTargetField()], [$event->getRefererOuuid()]);
                    $revision->setRawData($data);

                    $this->finalizeDraft($revision, $form, null, false);
                } else {
                    $this->discardDraft($revision);
                }
            } catch (LockedException $e) {
                $this->logger->error('service.data.update_referrers_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $key[0],
                    EmsFields::LOG_OUUID_FIELD => $key[1],
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                ]);
            }
        }


        foreach ($event->getToCreateOuuids() as $ouuid) {
            $key = explode(':', $ouuid);
            try {
                $revision = $this->initNewDraft($key[0], $key[1]);
                $data = $revision->getRawData();
                if (empty($data[$event->getTargetField()])) {
                    $data[$event->getTargetField()] = [];
                }
                if (! in_array($event->getRefererOuuid(), $data[$event->getTargetField()])) {
                    $data[$event->getTargetField()][] = $event->getRefererOuuid();
                    $revision->setRawData($data);

                    $this->finalizeDraft($revision, $form, null, false);
                } else {
                    $this->discardDraft($revision);
                }
            } catch (LockedException $e) {
                $this->logger->error('service.data.update_referrers_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $key[0],
                    EmsFields::LOG_OUUID_FIELD => $key[1],
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                ]);
            }
        }
    }

    public function createAndMapIndex(Environment $environment): void
    {
        $indexName = $environment->getAlias() . AppController::getFormatedTimestamp();
        $this->client->indices()->create([
            'index' => $indexName,
            'body' => $this->environmentService->getIndexAnalysisConfiguration(),
        ]);

        foreach ($this->contentTypeService->getAll() as $contentType) {
            $this->contentTypeService->updateMapping($contentType, $indexName);
        }

        $this->client->indices()->putAlias([
            'index' => $indexName,
            'name' => $environment->getAlias()
        ]);
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

        foreach (explode(',', $contentTypesCommaList) as $contentTypeName) {
            $contentType = $this->contentTypeService->getByName($contentTypeName);
            if ($contentType->getBusinessIdField() && count($ouuids) > 0) {
                $result = $this->client->search([
                    'index' => $contentType->getEnvironment()->getAlias(),
                    'body' => [
                        'size' => sizeof($ouuids),
                        '_source' => $contentType->getBusinessIdField(),
                        'query' => [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            '_contenttype' => $contentType->getName()
                                        ]
                                    ],
                                    [
                                        'terms' => [
                                            $contentType->getBusinessIdField() => $items
                                        ]
                                    ],
                                ]
                            ]
                        ]

                    ],
                    'size' => 100,
                    "scroll" => self::SCROLL_TIMEOUT,
                ]);

                while (count($result['hits']['hits'] ?? []) > 0) {
                    foreach ($result['hits']['hits'] as $hits) {
                        $key = sprintf('%s:%s', $contentType->getName(), $hits['_id']);
                        $ouuids[$hits['_source'][$contentType->getBusinessIdField()]] = $key;
                        $this->cacheOuuids[$contentTypesCommaList][$contentType->getBusinessIdField()] = $key;
                    }
                    $result = $this->client->scroll([
                        'scroll_id' => $result['_scroll_id'],
                        'scroll' =>  self::SCROLL_TIMEOUT,
                    ]);
                }
            }
        }
        return array_values($ouuids);
    }

    public function getDataLink(string $contentTypesCommaList, string $businessId): ?string
    {
        return $this->getDataLinks($contentTypesCommaList, [$businessId])[0] ?? $businessId;
    }



    public function hitFromBusinessIdToDataLink(ContentType $contentType, string $ouuid, array $rawData) : Document
    {
        $revision = $this->getEmptyRevision($contentType, null);
        $revision->setRawData($rawData);
        $revision->setOuuid($ouuid);
        $revisionType = $this->formFactory->create(RevisionType::class, $revision, ['migration' => true, 'raw_data' => $revision->getRawData(), 'with_warning' => false]);
        $result = $this->walkRecursive($revisionType->get('data'), $rawData, function (string $name, $data, DataFieldType $dataFieldType, DataField $dataField) {
            if ($data !== null && (!is_array($data) || count($data) > 0)) {
                if ($dataFieldType->isVirtual()) {
                    return $data;
                }

                if (!$dataFieldType instanceof DataLinkFieldType) {
                    return [$name => $data];
                }

                $typesList = $dataField->getFieldType()->getDisplayOption('type');
                if ($typesList == null) {
                    return [$name => $data];
                }

                if (is_string($data)) {
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
    }

    public function lockRevisions(ContentType $contentType, \DateTime $until, string $by): int
    {
        try {
            return $this->revRepository->lockRevisions($contentType, $until, $by, true, false);
        } catch (LockedException $e) {
            $this->logger->error('service.data.lock_revisions_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_USERNAME_FIELD => $by,
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);
        }
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
    }

    public function getAllDrafts(): array
    {
        return $this->revRepository->findAllDrafts();
    }
}
