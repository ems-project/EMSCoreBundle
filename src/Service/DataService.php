<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Notification;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Event\RevisionFinalizeDraftEvent;
use EMS\CoreBundle\Event\RevisionNewDraftEvent;
use EMS\CoreBundle\Event\UpdateRevisionReferersEvent;
use EMS\CoreBundle\Exception\DataStateException;
use EMS\CoreBundle\Exception\DuplicateOuuidException;
use EMS\CoreBundle\Exception\HasNotCircleException;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\PrivilegeException;
use EMS\CoreBundle\Form\DataField\CollectionFieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\DataLinkFieldType;
use EMS\CoreBundle\Form\DataField\FormFieldType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\Revision\PostProcessingService;
use EMS\CoreBundle\Twig\AppExtension;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\Error;

/**
 * @todo Move Revision related logic to RevisionService
 */
class DataService
{
    final public const int ALGO = OPENSSL_ALGO_SHA1;
    protected const SCROLL_TIMEOUT = '1m';

    private false|\OpenSSLAsymmetricKey|null $private_key = null;
    private ?string $public_key = null;

    /** @var array<mixed> */
    private array $cacheBusinessKey = [];
    /** @var array<mixed> */
    private array $cacheOuuids = [];
    protected EntityManager $em;

    public function __construct(
        protected Registry $doctrine,
        protected AuthorizationCheckerInterface $authorizationChecker,
        protected TokenStorageInterface $tokenStorage,
        protected string $lockTime,
        protected ElasticaService $elasticaService,
        protected Mapping $mapping,
        protected string $instanceId,
        protected FormFactoryInterface $formFactory,
        protected Container $container,
        protected FormRegistryInterface $formRegistry,
        protected EventDispatcherInterface $dispatcher,
        protected ContentTypeService $contentTypeService,
        string $privateKey,
        protected LoggerInterface $logger,
        private readonly LoggerInterface $auditLogger,
        private readonly StorageManager $storageManager,
        protected TwigEnvironment $twig,
        protected UserService $userService,
        protected RevisionRepository $revRepository,
        private readonly EnvironmentService $environmentService,
        private readonly SearchService $searchService,
        private readonly IndexService $indexService,
        private readonly bool $preGeneratedOuuids,
        private readonly PostProcessingService $postProcessingService,
    ) {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        $this->em = $em;

        if (!empty($privateKey)) {
            try {
                if (false === $privateKeyContent = \file_get_contents($privateKey)) {
                    throw new \RuntimeException(\sprintf('Could not open file in "%s"', $privateKey));
                }

                $this->private_key = \openssl_pkey_get_private($privateKeyContent);
            } catch (\Exception $e) {
                $this->logger->warning('service.data.not_able_to_load_the_private_key', [
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    'private_key_filename' => $privateKey,
                ]);
            }
        }
    }

    public function unlockRevision(Revision $revision, ?string $lockerUsername = null): void
    {
        $lockerUsername ??= $this->userService->getCurrentUser()->getUsername();

        if ($revision->getLockBy() === $lockerUsername && $revision->getLockUntil() > (new \DateTime())) {
            $this->revRepository->unlockRevision(Type::integer($revision->getId()));
        }
    }

    /**
     * @throws LockedException
     * @throws PrivilegeException
     * @throws \Exception
     */
    public function lockRevision(Revision $revision, ?Environment $publishEnv = null, bool $super = false, ?string $username = null, ?\DateTime $lockTime = null): string
    {
        if (null !== $publishEnv && !$this->authorizationChecker->isGranted($revision->giveContentType()->role(ContentTypeRoles::PUBLISH))) {
            throw new PrivilegeException($revision, 'You don\'t have publisher role for this content');
        }
        if (null !== $publishEnv && !empty($publishEnv->getCircles()) && !$this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT') && !$this->userService->inMyCircles($publishEnv->getCircles())) {
            throw new PrivilegeException($revision, 'You don\'t share any circle with this content');
        }
        if (null === $username && null === $publishEnv && !empty($revision->giveContentType()->getCirclesField()) && !empty($revision->getRawData()[$revision->giveContentType()->getCirclesField()])) {
            if (!$this->userService->inMyCircles($revision->getRawData()[$revision->giveContentType()->getCirclesField()] ?? [])) {
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
            $lockerUsername = $token->getUserIdentifier();
        } else {
            $lockerUsername = $username;
        }

        if ($revision->isLockedFor($lockerUsername)) {
            throw new LockedException($revision);
        }

        $twigExtension = $this->container->get('app.twig_extension');

        if (!$username && $twigExtension instanceof AppExtension && !$twigExtension->oneGranted($revision->giveContentType()->getFieldType()->getFieldsRoles(), $super)) {
            throw new PrivilegeException($revision);
        }
        // TODO: test circles

        $revision->setLockBy($lockerUsername);

        $lockTime ??= new \DateTime($this->lockTime);
        $revision->setLockUntil($lockTime);

        $em->flush();

        return $lockerUsername;
    }

    /**
     * @return Revision[]
     */
    public function getAllDeleted(ContentType $contentType): array
    {
        /** @var Revision[] $revisions */
        $revisions = $this->revRepository->findBy([
            'deleted' => true,
            'contentType' => $contentType,
            'endTime' => null,
        ], [
            'modified' => 'asc',
        ]);

        return $revisions;
    }

    /**
     * @return string[]
     */
    public function getDataCircles(Revision $revision): array
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

    public function getRevisionByEnvironment(string $ouuid, ContentType $contentType, Environment $environment): Revision
    {
        return $this->revRepository->findByEnvironment($ouuid, $contentType, $environment);
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<mixed>         $objectArray
     *
     * @throws \Throwable
     */
    public function propagateDataToComputedField(FormInterface $form, array &$objectArray, ContentType $contentType, string $type, ?string $ouuid, bool $migration = false, bool $finalize = true): bool
    {
        return $this->postProcessingService->postProcessing($form, $contentType, $objectArray, [
            '_id' => $ouuid,
            'migration' => $migration,
            'finalize' => $finalize,
            'rootObject' => $objectArray,
        ]);
    }

    public function getPostProcessing(): PostProcessingService
    {
        return $this->postProcessingService;
    }

    /**
     * @param string[] $keys
     *
     * @return string[]
     */
    public function getBusinessIds(array $keys): array
    {
        /** @var array<string, string[]> $items */
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
                    'index' => $contentType->giveEnvironment()->getAlias(),
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

    /**
     * @param array<mixed> $hit
     */
    public function hitToBusinessDocument(ContentType $contentType, array $hit): DocumentInterface
    {
        $revision = $this->getEmptyRevision($contentType);
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

        return Document::fromData($contentType, $revision->giveOuuid(), $result);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function walkRecursive(FormInterface $form, mixed $rawData, callable $callback): mixed
    {
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $form->getConfig()->getType()->getInnerType();
        /** @var DataField $dataField */
        $dataField = $form->getNormData();

        if (!$dataFieldType->isContainer()) {
            return $callback($form->getName(), $rawData, $dataFieldType, $dataField);
        }

        $output = [];
        if ($form instanceof \IteratorAggregate) {
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
                    if (!$childType->isVirtual($subOptions)) {
                        $childData = $rawData[$child->getName()] ?? null;
                    }
                    $output = \array_merge($output, $this->walkRecursive($child, $childData, $callback));
                }
            }
        }

        return $callback($form->getName(), $output, $dataFieldType, $dataField);
    }

    public function convertInputValues(DataField $dataField): void
    {
        foreach ($dataField->getChildren() as $child) {
            $this->convertInputValues($child);
        }
        if (!empty($dataField->getFieldType()) && !empty($dataField->getFieldType()->getType())) {
            /** @var DataFieldType $dataFieldType */
            $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
            if ($dataFieldType instanceof DataFieldType) {
                $dataFieldType->convertInput($dataField);
            }
        }
    }

    public static function isInternalField(string $fieldName): bool
    {
        return '_ems_internal_deleted' === $fieldName;
    }

    public function generateInputValues(DataField $dataField): void
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
     * @param array<mixed> $rawdata
     *
     * @throws \Exception
     */
    public function createData(?string $ouuid, array $rawdata, ContentType $contentType): Revision
    {
        $now = new \DateTime();
        $until = new \DateTime($this->lockTime);
        $newRevision = new Revision();
        $newRevision->setContentType($contentType);
        if (null !== $ouuid) {
            $newRevision->setOuuid($ouuid);
        }
        $newRevision->setStartTime($now);
        $newRevision->setEndTime(null);
        $newRevision->setDeleted(false);
        $newRevision->setDraft(true);
        $lockBy = $this->userService->getCurrentUser()->getUserIdentifier();
        $newRevision->setLockBy($lockBy);

        $newRevision->setLockUntil($until);
        $newRevision->setRawData($rawdata);

        return $this->createNewRevision($newRevision);
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
        Json::normalize($objectArray);
        $json = Json::encode($objectArray);

        $hash = $this->storageManager->computeStringHash($json);
        $objectArray[Mapping::HASH_FIELD] = $hash;

        if ($this->private_key) {
            $signature = null;
            if (\openssl_sign($json, $signature, $this->private_key, OPENSSL_ALGO_SHA1)) {
                $objectArray[Mapping::SIGNATURE_FIELD] = \base64_encode((string) $signature);
            } else {
                $this->logger->warning('service.data.not_able_to_sign', [
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => \openssl_error_string(),
                ]);
            }
        }

        return $hash;
    }

    /**
     * @return array<mixed>
     */
    public function sign(Revision $revision, bool $silentPublish = false): array
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
            $revision->updateVersionNextTag();
        }

        $hash = $this->signRaw($objectArray);
        $revision->setSha1($hash);

        $revision->setRawData($objectArray);

        return $objectArray;
    }

    public function getPublicKey(): ?string
    {
        if ($this->private_key && empty($this->public_key)) {
            $certificate = \openssl_pkey_get_private($this->private_key);
            if (false === $certificate) {
                throw new \RuntimeException('Private key not found');
            }
            $details = \openssl_pkey_get_details($certificate);
            $this->public_key = $details ? ($details['key'] ?? null) : null;
        }

        return $this->public_key;
    }

    /**
     * @return ?array<mixed, mixed>
     */
    public function getCertificateInfo(): ?array
    {
        if ($this->private_key) {
            $certificate = \openssl_pkey_get_private($this->private_key);
            if (false === $certificate) {
                throw new \RuntimeException('Private key not found');
            }

            $details = \openssl_pkey_get_details($certificate);

            return $details ?: null;
        }

        return null;
    }

    public function testIntegrityInIndexes(Revision $revision): void
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
                $document = $this->searchService->getDocument($contentType, $revision->giveOuuid(), $environment);
                $indexedItem = $document->getSource();

                Json::normalize($indexedItem);

                if (isset($indexedItem[Mapping::PUBLISHED_DATETIME_FIELD])) {
                    unset($indexedItem[Mapping::PUBLISHED_DATETIME_FIELD]);
                }

                if (isset($indexedItem[Mapping::HASH_FIELD])) {
                    if ($indexedItem[Mapping::HASH_FIELD] != $revision->getSha1()) {
                        $this->logger->warning('service.data.hash_mismatch', [
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                            EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
                            'index_hash' => $indexedItem[Mapping::HASH_FIELD],
                            'db_hash' => $revision->getSha1(),
                            'label' => $revision->getLabel(),
                        ]);
                    }
                    unset($indexedItem[Mapping::HASH_FIELD]);

                    if (isset($indexedItem[Mapping::SIGNATURE_FIELD])) {
                        $binary_signature = \base64_decode((string) $indexedItem[Mapping::SIGNATURE_FIELD]);
                        unset($indexedItem[Mapping::SIGNATURE_FIELD]);
                        $data = Json::encode($indexedItem);

                        // Check signature

                        $ok = 0;
                        if (null !== $publicKey = $this->getPublicKey()) {
                            $ok = \openssl_verify($data, $binary_signature, $publicKey, self::ALGO);
                        }

                        if (0 === $ok) {
                            $this->logger->info('service.data.check_signature_failed', [
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getLabel(),
                                EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
                                'label' => $revision->getLabel(),
                            ]);
                        } elseif (1 !== $ok) { // 1 means signature is ok
                            $this->logger->info('service.data.error_check_signature', [
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                                EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
                                EmsFields::LOG_ERROR_MESSAGE_FIELD => \openssl_error_string(),
                                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                                'label' => $revision->getLabel(),
                            ]);
                        }
                    } else {
                        $data = Json::encode($indexedItem);
                        if ($this->private_key) {
                            $this->logger->info('service.data.revision_not_signed', [
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                                EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
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
                            EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
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
                        EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
                        'label' => $revision->getLabel(),
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->error('service.data.integrity_failed', [
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    'label' => $revision->getLabel(),
                ]);
            }
        }
    }

    /**
     * @return FormInterface<mixed>
     *
     * @throws \Exception
     */
    public function buildForm(Revision $revision): FormInterface
    {
        if (null == $revision->getDatafield()) {
            $this->loadDataStructure($revision);
        }

        // Get the form from Factory
        $builder = $this->formFactory->createBuilder(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);
        $form = $builder->getForm();

        return $form;
    }

    public function refresh(Environment $environment): bool
    {
        return $this->elasticaService->refresh($environment->getAlias());
    }

    /**
     * @param ?FormInterface<mixed> $form
     *
     * @throws DataStateException
     * @throws \Exception
     * @throws \Throwable
     */
    public function finalizeDraft(Revision $revision, ?FormInterface $form = null, ?string $username = null, bool $computeFields = true): Revision
    {
        if ($revision->getDeleted()) {
            throw new \Exception('Can not finalized a deleted revision');
        }
        if (null == $form) {
            if (null == $revision->getDatafield()) {
                $this->loadDataStructure($revision);
            }

            // Get the form from Factory
            $builder = $this->formFactory->createBuilder(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);
            $form = $builder->getForm();
        }

        if (empty($username)) {
            $token = $this->tokenStorage->getToken();
            if (null === $token) {
                throw new \RuntimeException('Unexpected null token');
            }
            $username = $token->getUserIdentifier();
        }
        $this->lockRevision($revision, null, false, $username);

        $em = $this->doctrine->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository(Revision::class);

        // TODO: test if draft and last version publish in

        if (!empty($revision->getAutoSave())) {
            throw new DataStateException('An auto save is pending, it can not be finalized.');
        }

        if (!$revision->hasOuuid() && $this->preGeneratedOuuids) {
            $revision->setOuuid(Uuid::uuid4()->toString());
        }

        $objectArray = $revision->getRawData();

        $this->updateDataStructure($revision->giveContentType()->getFieldType(), $form->get('data')->getNormData());
        if ($computeFields && $this->propagateDataToComputedField($form->get('data'), $objectArray, $revision->giveContentType(), $revision->giveContentType()->getName(), $revision->getOuuid())) {
            $revision->setRawData($objectArray);
        }
        $this->setMetaFields($revision);

        $previousObjectArray = null;

        $revision->setRawDataFinalizedBy($username);

        $objectArray = $this->sign($revision);

        if ($this->isValid($form, null, $objectArray)) {
            $ouuid = $revision->getOuuid();
            $this->indexService->indexRevision($revision);
            if (null !== $ouuid) {
                $item = $repository->findByOuuidContentTypeAndEnvironment($revision);
                if ($item) {
                    $this->lockRevision($item, null, false, $username);
                    $previousObjectArray = $item->getRawData();
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
            $this->dispatcher->dispatch(new RevisionFinalizeDraftEvent($revision));

            $this->auditLogger->notice('log.revision.finalized', LogRevisionContext::update($revision));

            try {
                $this->postFinalizeTreatment($revision->giveContentType()->getName(), $revision->giveOuuid(), $form->get('data'), $previousObjectArray);
            } catch (\Exception $e) {
                $this->logger->warning('service.data.post_finalize_failed', [
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_ENVIRONMENT_FIELD => $revision->giveContentType()->giveEnvironment()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    'label' => $revision->getLabel(),
                ]);
            }
        } else {
            $this->logFormErrors($form);
        }

        return $revision;
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function logFormErrors(FormInterface $form): void
    {
        $formErrors = $form->getErrors(true);
        foreach ($formErrors as $formError) {
            if (!$formError instanceof FormError) {
                continue;
            }

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

            $fieldName = $dataField->giveFieldType()->getDisplayOption('label', $dataField->giveFieldType()->getName());
            $errorPath = '';

            $parent = $fieldForm;
            while (($parent = $parent->getParent()) !== null) {
                $parentNormData = $parent->getNormData();

                if ($parentNormData instanceof DataField && null !== $parentNormData->giveFieldType()->getParent()) {
                    $errorPath .= $parentNormData->giveFieldType()->getDisplayOption('label', $parentNormData->giveFieldType()->getName()).' > ';
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
     * Parcours all fields and call DataFieldsType postFinalizeTreament function.
     *
     * @param FormInterface<mixed> $form
     */
    public function postFinalizeTreatment(string $type, string $id, FormInterface $form, mixed $previousObjectArray = null): void
    {
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
     * @throws \Exception
     * @throws NotFoundHttpException
     */
    public function getNewestRevision(string $type, string $ouuid): Revision
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var ContentTypeRepository $contentTypeRepo */
        $contentTypeRepo = $em->getRepository(ContentType::class);
        $contentTypes = $contentTypeRepo->findBy([
            'name' => $type,
            'deleted' => false,
        ]);

        if (1 != \count($contentTypes)) {
            throw new NotFoundHttpException('Unknown content type');
        }
        $contentType = $contentTypes[0];

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository(Revision::class);
        /** @var Revision[] $revisions */
        $revisions = $repository->findBy([
            'ouuid' => $ouuid,
            'endTime' => null,
            'contentType' => $contentType,
            'deleted' => false,
        ]);

        if (1 == \count($revisions)) {
            $endTime = $revisions[0]->getEndTime();

            if (null === $endTime) {
                return $revisions[0];
            } else {
                throw new NotFoundHttpException('Revision for ouuid '.$ouuid.' and contenttype '.$type.' with end time '.$endTime->format(\DateTimeInterface::ATOM));
            }
        } elseif (0 == \count($revisions)) {
            throw new NotFoundHttpException('Revision not found for ouuid '.$ouuid.' and contenttype '.$type);
        } else {
            throw new \Exception('Too much newest revisions available for ouuid '.$ouuid.' and contenttype '.$type);
        }
    }

    /**
     * @param array<mixed>|null $rawData
     *
     * @throws DuplicateOuuidException
     * @throws HasNotCircleException
     * @throws \Throwable
     */
    public function newDocument(ContentType $contentType, ?string $ouuid = null, ?array $rawData = null, ?string $username = null): Revision
    {
        $this->hasCreateRights($contentType);

        $revision = new Revision();

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
                } catch (\Throwable) {
                    $this->logger->error('service.data.default_value_error', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                        EmsFields::LOG_OUUID_FIELD => $ouuid,
                    ]);
                }
            } catch (Error $e) {
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
        if (null !== $ouuid) {
            $revision->setOuuid($ouuid);
        }
        $revision->setDeleted(false);
        $revision->setStartTime($now);
        $revision->setEndTime(null);
        $revision->setLockBy($username);
        $revision->setLockUntil(new \DateTime($this->lockTime));

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
                        // set first of my circles
                        if (!empty($currentUser->getCircles())) {
                            $revision->setRawData(\array_merge($revision->getRawData(), [$contentType->getCirclesField() => $currentUser->getCircles()[0]]));
                            $revision->setCircles([$currentUser->getCircles()[0]]);
                        }
                    }
                }
            }
        }
        $this->setMetaFields($revision);

        return $this->createNewRevision($revision);
    }

    /**
     * @throws HasNotCircleException
     */
    public function hasCreateRights(ContentType $contentType): void
    {
        if ($this->userService->isCliSession()) {
            return;
        }
        $userCircles = $this->userService->getCurrentUser()->getCircles();
        $environment = $contentType->giveEnvironment();
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

    public function setMetaFields(Revision $revision): void
    {
        $this->setCircles($revision);
        $this->setLabelField($revision);

        $revision->setVersionMetaFields();
    }

    private function setCircles(Revision $revision): void
    {
        $objectArray = $revision->getRawData();
        if (!empty($revision->giveContentType()->getCirclesField()) && isset($objectArray[$revision->giveContentType()->getCirclesField()]) && !empty($objectArray[$revision->giveContentType()->getCirclesField()])) {
            $revision->setCircles(\is_array($objectArray[$revision->giveContentType()->getCirclesField()]) ? $objectArray[$revision->giveContentType()->getCirclesField()] : [$objectArray[$revision->giveContentType()->getCirclesField()]]);
        } else {
            $revision->setCircles(null);
        }
    }

    private function setLabelField(Revision $revision): void
    {
        $objectArray = $revision->getRawData();
        $labelField = $revision->giveContentType()->getLabelField();
        if (!empty($labelField)
                && isset($objectArray[$labelField])
                && !empty($objectArray[$labelField])) {
            $revision->setLabelField($objectArray[$labelField]);
        } else {
            $revision->setLabelField(null);
        }
    }

    /**
     * @throws LockedException
     * @throws PrivilegeException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Exception
     */
    public function initNewDraft(string $type, string $ouuid, ?Revision $fromRev = null, ?string $username = null): Revision
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var ContentTypeRepository $contentTypeRepo */
        $contentTypeRepo = $em->getRepository(ContentType::class);
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
            $revision->getDataField()->propagateOuuid($revision->giveOuuid());
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
            $revision->tasksClear();

            $lockedBy = $this->lockRevision($newDraft, null, false, $username);
            $newDraft->setAutoSaveBy($lockedBy);

            $em->persist($revision);
            $em->persist($newDraft);
            $em->flush();

            $this->auditLogger->info('log.revision.draft.created', LogRevisionContext::update($revision));

            $this->dispatcher->dispatch(new RevisionNewDraftEvent($newDraft));

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
    public function discardDraft(Revision $revision, bool $super = false, ?string $username = null): ?int
    {
        $this->lockRevision($revision, null, $super, $username);

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository(Revision::class);

        if (!$revision->getDraft() || null != $revision->getEndTime()) {
            throw new BadRequestHttpException('Only authorized on a draft');
        }

        $hasPreviousRevision = 0;

        if (null != $revision->getOuuid()) {
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

            if (1 == (\is_countable($result) ? \count($result) : 0)) {
                /** @var Revision $previous */
                $previous = $result[0];
                $this->lockRevision($previous, null, $super, $username);
                $previous->setEndTime(null);
                $previous->tasksRollback($revision);
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

    public function delete(string $type, string $ouuid, ?string $username = null): void
    {
        $contentType = $this->contentTypeService->giveByName($type);

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var RevisionRepository $repository */
        $repository = $em->getRepository(Revision::class);

        $revisions = $repository->findBy([
            'ouuid' => $ouuid,
            'contentType' => $contentType]);

        $username ??= $this->userService->getCurrentUser()->getUsername();

        /** @var Revision $revision */
        foreach ($revisions as $revision) {
            $this->lockRevision(revision: $revision, username: $username);

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
            $revision->setDeletedBy($username);

            if (null === $revision->getEndTime()) {
                $this->auditLogger->notice('log.revision.deleted', LogRevisionContext::delete($revision));
            }

            $em->persist($revision);
        }
        $em->flush();

        $this->elasticaService->refresh($contentType->giveEnvironment()->getAlias());
    }

    public function trashEmpty(ContentType $contentType, string ...$ouuids): void
    {
        $revisions = $this->revRepository->findTrashRevisions($contentType, ...$ouuids);

        foreach ($revisions as $revision) {
            $this->lockRevision($revision);
            $this->em->remove($revision);
        }
        $this->em->flush();
    }

    public function trashPutBackAsDraft(ContentType $contentType, string ...$ouuids): ?Revision
    {
        $revisions = $this->revRepository->findTrashRevisions($contentType, ...$ouuids);

        foreach ($revisions as $revision) {
            $revision->setDeleted(false);
            $revision->setDeletedBy(null);
            if (null === $revision->getEndTime()) {
                $this->lockRevision($revision);
                $revision->setDraft(true);
                $this->auditLogger->notice('log.revision.restored', LogRevisionContext::update($revision));
            } else {
                $revision->enableSelfUpdate();
            }
            $this->em->persist($revision);
        }
        $this->em->flush();

        return 1 === \count($ouuids) ? \array_shift($revisions) : null;
    }

    /**
     * @throws \Exception
     */
    public function updateDataStructure(FieldType $meta, DataField $dataField): void
    {
        if (null === $fieldType = $dataField->getFieldType()) {
            return;
        }

        $dataFieldType = $this->formRegistry->getType($fieldType->getType())->getInnerType();
        if (!$dataFieldType instanceof DataFieldType && !self::isInternalField($fieldType->getName())) {
            $this->logger->warning('service.data.not_a_data_field', ['field_name' => $fieldType->getName()]);

            return;
        }

        if ($dataFieldType instanceof DataFieldType && !$dataFieldType::isContainer()) {
            return;
        }

        foreach ($meta->getChildren() as $field) {
            if ($field->getDeleted() || null !== $dataField->__get('ems_'.$field->getName())) {
                continue;
            }

            if (FormFieldType::class === $field->getType()) {
                /** @var FormFieldType $formFieldType */
                $formFieldType = $this->formRegistry->getType($field->getType())->getInnerType();
                $formMeta = $formFieldType->getReferredFieldType($field);

                $this->updateDataStructure($formMeta, $dataField);
                continue;
            }

            $child = new DataField();
            $child->setFieldType($field);
            $child->setOrderKey($field->getOrderKey());
            $child->setParent($dataField);
            $dataField->addChild($child);

            if (isset($field->getDisplayOptions()['defaultValue'])) {
                $child->setEncodedText($field->getDisplayOptions()['defaultValue']);
            }

            if (CollectionFieldType::class !== $field->getType()) {
                $this->updateDataStructure($field, $child);
            }
        }
    }

    /**
     * Assign data in dataValues based on the elastic index content.
     *
     * @param array<mixed> $elasticIndexDatas
     */
    public function updateDataValue(DataField $dataField, array &$elasticIndexDatas, bool $isMigration = false): void
    {
        $dataFieldType = $this->formRegistry->getType($dataField->giveFieldType()->getType())->getInnerType();
        if ($dataFieldType instanceof DataFieldType) {
            $fieldType = $dataField->getFieldType();
            if (null === $fieldType) {
                throw new \RuntimeException('Unexpected null fieldType');
            }

            $fieldNames = $dataFieldType->getJsonNames($fieldType);
            if (0 === \count($fieldNames)) {// Virtual container
                /** @var DataField $child */
                foreach ($dataField->getChildren() as $child) {
                    $this->updateDataValue($child, $elasticIndexDatas, $isMigration);
                }
            } else {
                if ($dataFieldType->isVirtual($dataField->giveFieldType()->getOptions())) {
                    $treatedFields = $dataFieldType->importData($dataField, $elasticIndexDatas, $isMigration);
                    foreach ($treatedFields as $fieldName) {
                        unset($elasticIndexDatas[$fieldName]);
                    }
                } else {
                    foreach ($fieldNames as $fieldName) {
                        if (\array_key_exists($fieldName, $elasticIndexDatas)) {
                            $treatedFields = $dataFieldType->importData($dataField, $elasticIndexDatas[$fieldName], $isMigration);
                            foreach ($treatedFields as $treatedFieldName) {
                                unset($elasticIndexDatas[$treatedFieldName]);
                            }
                        }
                    }
                }
            }
        } elseif (!DataService::isInternalField($dataField->giveFieldType()->getName())) {
            $this->logger->warning('service.data.not_a_data_field', [
                'field_name' => $dataField->giveFieldType()->getName(),
            ]);
        }
    }

    /**
     * @throws \Exception
     */
    public function loadDataStructure(Revision $revision, bool $ignoreNotConsumed = false): void
    {
        $data = new DataField();
        $data->setFieldType($revision->giveContentType()->getFieldType());
        $data->setOrderKey($revision->giveContentType()->getFieldType()->getOrderKey());
        $data->setRawData($revision->getRawData());
        $revision->setDataField($data);
        $this->updateDataStructure($revision->giveContentType()->getFieldType(), $data);
        $object = $revision->getRawData();
        $this->updateDataValue($data, $object);

        foreach (\array_keys($object) as $key) {
            if (\is_string($key) && \str_starts_with($key, '_')) {
                unset($object[$key]);
            }
        }

        if ($ignoreNotConsumed) {
            return;
        }

        if (\count($object) > 0) {
            $html = self::arrayToHtml($object);

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

    public function reloadData(Revision $revision, bool $flush = true): int
    {
        if ($revision->hasHash()) {
            $revisionHash = $revision->getHash();
        } else {
            $revisionHash = null;
        }
        $reloadRevision = clone $revision;

        $finalizedBy = false;
        $finalizationDate = false;
        $objectArray = $reloadRevision->getRawData();
        if (isset($objectArray[Mapping::FINALIZED_BY_FIELD])) {
            $finalizedBy = $objectArray[Mapping::FINALIZED_BY_FIELD];
        }
        if (isset($objectArray[Mapping::FINALIZATION_DATETIME_FIELD])) {
            $finalizationDate = $objectArray[Mapping::FINALIZATION_DATETIME_FIELD];
        }

        $builder = $this->formFactory->createBuilder(RevisionType::class, $reloadRevision, [
            'raw_data' => $reloadRevision->getRawData(),
            'with_warning' => false,
        ]);
        $form = $builder->getForm();

        $objectArray = $reloadRevision->getRawData();
        $this->updateDataStructure($reloadRevision->giveContentType()->getFieldType(), $form->get('data')->getNormData());
        $this->propagateDataToComputedField($form->get('data'), $objectArray, $reloadRevision->giveContentType(), $reloadRevision->giveContentType()->getName(), $reloadRevision->getOuuid(), false, false);

        if (false !== $finalizedBy) {
            $objectArray[Mapping::FINALIZED_BY_FIELD] = $finalizedBy;
        }
        if (false !== $finalizationDate) {
            $objectArray[Mapping::FINALIZATION_DATETIME_FIELD] = $finalizationDate;
        }

        $reloadRevision->setRawData($objectArray);
        $this->sign($reloadRevision);

        if ($reloadRevision->getHash() === $revisionHash) {
            return 0;
        }

        $revision->setRawData($objectArray);
        $this->sign($revision);

        $revision->enableSelfUpdate();
        $this->em->persist($revision);

        if ($flush) {
            $this->em->flush();
        }

        return 1;
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return mixed
     */
    public function getSubmitData(FormInterface $form)
    {
        $out = $form->getViewData();

        if ($form instanceof Form) {
            $iteratedOn = $form->getIterator();
        } else {
            $iteratedOn = $form->all();
        }

        foreach ($iteratedOn as $subForm) {
            if ($subForm->getConfig()->getCompound()) {
                $out[$subForm->getName()] = $this->getSubmitData($subForm);
            }
        }

        return $out;
    }

    public function getEmptyRevision(ContentType $contentType, ?string $user = null): Revision
    {
        $now = new \DateTime();
        $until = new \DateTime($this->lockTime);
        $newRevision = new Revision();
        $newRevision->setContentType($contentType);
        $newRevision->addEnvironment($contentType->giveEnvironment());
        $newRevision->setStartTime($now);
        $newRevision->setEndTime(null);
        $newRevision->setDeleted(false);
        $newRevision->setDraft(true);
        if ($user) {
            $newRevision->setLockBy($user);
        }
        $newRevision->setLockUntil($until);
        $newRevision->setRawData([]);

        return $newRevision;
    }

    /**
     * @param array<mixed> $array
     */
    public static function arrayToHtml(array $array): string
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
     * @param FormInterface<mixed> $form
     * @param ?array<mixed>        $masterRawData
     *
     * @throws \Exception
     */
    public function isValid(FormInterface &$form, ?DataField $parent = null, ?array &$masterRawData = null): bool
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
        if (null !== $dataField->getFieldType()?->getType()) {
            /** @var DataFieldType $dataFieldType */
            $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
            $dataFieldType->isValid($dataField, $parent, $masterRawData);
        }
        $isValid = true;

        if ($dataFieldType instanceof FormFieldType) {
            foreach ($form->all() as $child) {
                $this->isValid($child, $dataField, $masterRawData);
            }
        }
        if (null !== $dataFieldType && $dataFieldType->isContainer()) {// If dataField is container or type is null => Container => Recursive
            $formChildren = $form->all();
            foreach ($formChildren as $child) {
                if ($child instanceof FormInterface) {
                    $tempIsValid = $this->isValid($child, $dataField, $masterRawData); // Recursive
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
     * @throws \Exception
     */
    public function getRevisionById(int $id, ContentType $type): Revision
    {
        $em = $this->doctrine->getManager();

        /** @var ContentTypeRepository $contentTypeRepo */
        $contentTypeRepo = $em->getRepository(ContentType::class);
        $contentTypes = $contentTypeRepo->findBy([
            'name' => $type->getName(),
            'deleted' => false,
        ]);

        if (1 != \count($contentTypes)) {
            throw new NotFoundHttpException('Unknown content type');
        }
        $contentType = $contentTypes[0];
        /** @var RevisionRepository $repository */
        $repository = $em->getRepository(Revision::class);
        /** @var Revision[] $revisions */
        $revisions = $repository->findBy([
            'id' => $id,
            'endTime' => null,
            'contentType' => $contentType,
            'deleted' => false,
        ]);

        if (1 == \count($revisions)) {
            $endTime = $revisions[0]->getEndTime();

            if (null === $endTime) {
                return $revisions[0];
            } else {
                throw new \Exception('Revision for ouuid '.$id.' and contenttype '.$type.' with end time '.$endTime->format(\DateTimeInterface::ATOM));
            }
        } elseif (0 == \count($revisions)) {
            throw new NotFoundHttpException('Revision not found for id '.$id.' and contenttype '.$type);
        } else {
            throw new \Exception('Too much newest revisions available for ouuid '.$id.' and contenttype '.$type);
        }
    }

    /**
     * @param array<mixed> $rawData
     *
     * @throws LockedException
     * @throws PrivilegeException
     */
    public function replaceData(Revision $revision, array $rawData, string $replaceOrMerge = 'replace'): Revision
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

    /**
     * @param FormInterface<mixed> $form
     */
    public function getDataFieldsStructure(FormInterface $form): DataField
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

    /**
     * Call on UpdateRevisionReferersEvent. Will try to update referers objects.
     *
     * @throws DataStateException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws PrivilegeException
     * @throws \Throwable
     */
    public function updateReferers(UpdateRevisionReferersEvent $event): void
    {
        $form = null;
        foreach ($event->getToCleanOuuids() as $ouuid) {
            $key = \explode(':', (string) $ouuid);
            try {
                $revision = $this->initNewDraft($key[0], $key[1]);
                $data = $revision->getRawData();
                if (empty($data[$event->getTargetField()])) {
                    $data[$event->getTargetField()] = [];
                }
                if (\in_array($event->getRefererOuuid(), $data[$event->getTargetField()])) {
                    $data[$event->getTargetField()] = \array_diff($data[$event->getTargetField()], [$event->getRefererOuuid()]);
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
            $key = \explode(':', (string) $ouuid);
            try {
                $revision = $this->initNewDraft($key[0], $key[1]);
                $data = $revision->getRawData();
                if (empty($data[$event->getTargetField()])) {
                    $data[$event->getTargetField()] = [];
                }
                if (!\in_array($event->getRefererOuuid(), $data[$event->getTargetField()])) {
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
        $body = $this->environmentService->getIndexAnalysisConfiguration();
        $indexName = $environment->getNewIndexName();
        $this->mapping->createIndex($indexName, $body, $environment->getAlias());

        foreach ($this->contentTypeService->getAll() as $contentType) {
            $this->contentTypeService->updateMapping($contentType, $indexName);
        }
    }

    /**
     * @param string[] $businessIds
     *
     * @return string[]
     */
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
                    'index' => $contentType->giveEnvironment()->getAlias(),
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

    /**
     * @param array<mixed> $rawData
     */
    public function hitFromBusinessIdToDataLink(ContentType $contentType, string $ouuid, array $rawData): DocumentInterface
    {
        $revision = $this->getEmptyRevision($contentType);
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

                $typesList = $dataField->giveFieldType()->getDisplayOption('type');
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

        return Document::fromData($contentType, $ouuid, $result);
    }

    public function lockAllRevisions(\DateTime $until, string $by): int
    {
        try {
            return $this->revRepository->lockAllRevisions($until, $by);
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            $this->logger->error('service.data.unlock_revisions_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_USERNAME_FIELD => $by,
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);
        }

        return 0;
    }

    /**
     * @return Revision[]
     */
    public function getAllDrafts(): array
    {
        return $this->revRepository->findAllDrafts();
    }

    private function createNewRevision(Revision $revision): Revision
    {
        $ouuid = $revision->getOuuid();
        $contentType = $revision->giveContentType();

        $currentRevision = $ouuid ? $this->revRepository->getCurrentRevision($contentType, $ouuid) : null;
        if ($ouuid && $currentRevision && !$currentRevision->getDeleted()) {
            throw new DuplicateOuuidException($ouuid, $contentType->getName());
        }

        $this->em->getConnection()->beginTransaction();

        try {
            $restoredDraft = $currentRevision ? $this->trashPutBackAsDraft($contentType, $currentRevision->giveOuuid()) : null;

            if ($restoredDraft) {
                $restoredDraft->setDraft(false);
                $restoredDraft->setEndTime($revision->getStartTime());
                $this->unlockRevision($restoredDraft);
            }

            $this->em->persist($revision);
            $this->em->flush();
            $this->em->getConnection()->commit();
        } catch (\Throwable $e) {
            $this->em->getConnection()->rollBack();
            throw $e;
        }

        return $revision;
    }
}
