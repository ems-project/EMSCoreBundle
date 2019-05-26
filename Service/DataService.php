<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use EMS\CommonBundle\Helper\ArrayTool;
use EMS\CommonBundle\Helper\EmsFields;
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
use EMS\CoreBundle\Form\DataField\CollectionItemFieldType;
use EMS\CoreBundle\Form\DataField\ComputedFieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Monolog\Logger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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

class DataService
{

    const ALGO = OPENSSL_ALGO_SHA1;

    private $private_key;
    private $public_key;


    /**@var \Twig_Environment $twig*/
    protected $twig;
    /**@var Registry $doctrine */
    protected $doctrine;
    /**@var AuthorizationCheckerInterface $authorizationChecker*/
    protected $authorizationChecker;
    /**@var TokenStorageInterface $tokenStorage*/
    protected $tokenStorage;
    protected $lockTime;
    /**@Client $client*/
    protected $client;
    /**@var Mapping $mapping*/
    protected $mapping;
    protected $instanceId;
    protected $em;
    /** @var RevisionRepository */
    protected $revRepository;
    /**@var Session $session*/
    protected $session;
    /**@var FormFactoryInterface $formFactory*/
    protected $formFactory;
    protected $container;
    protected $appTwig;
    /**@var FormRegistryInterface*/
    protected $formRegistry;
    /**@var EventDispatcherInterface*/
    protected $dispatcher;
    /**@var ContentTypeService */
    protected $contentTypeService;
    /**@var UserService */
    protected $userService;
    /**@var Logger */
    protected $logger;

    public function __construct(
        Registry $doctrine,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        $lockTime,
        Client $client,
        Mapping $mapping,
        $instanceId,
        Session $session,
        FormFactoryInterface $formFactory,
        $container,
        FormRegistryInterface $formRegistry,
        EventDispatcherInterface $dispatcher,
        ContentTypeService $contentTypeService,
        $privateKey,
        Logger $logger
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
        $this->revRepository = $this->em->getRepository('EMSCoreBundle:Revision');
        $this->session = $session;
        $this->formFactory = $formFactory;
        $this->container = $container;
        $this->twig = $container->get('twig');
        $this->appTwig = $container->get('app.twig_extension');
        $this->formRegistry = $formRegistry;
        $this->dispatcher= $dispatcher;
        $this->contentTypeService = $contentTypeService;
        $this->userService = $container->get('ems.service.user');

        $this->public_key = null;
        $this->private_key = null;
        if (! empty($privateKey)) {
            try {
                $this->private_key = openssl_pkey_get_private(file_get_contents($privateKey));
            } catch (\Exception $e) {
                $this->session->getFlashBag()->add('warning', 'ems was not able to load the certificat: '.$e->getMessage());
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


    public function lockRevision(Revision $revision, $publishEnv = false, $super = false, $username = null)
    {



        if (!empty($publishEnv) && !$this->authorizationChecker->isGranted($revision->getContentType()->getPublishRole()?:'ROLE_PUBLISHER')) {
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
                throw new PrivilegeException($revision, 'A pending "'.$notification->getTemplate()->getName().'" notification is locking this content');
            }
        }



        $em = $this->doctrine->getManager();
        if ($username === null) {
            $lockerUsername = $this->tokenStorage->getToken()->getUsername();
        } else {
            $lockerUsername = $username;
        }
        $now = new \DateTime();
        if ($revision->getLockBy() != $lockerUsername && $now <  $revision->getLockUntil()) {
            throw new LockedException($revision);
        }

        if (!$username && !$this->container->get('app.twig_extension')->oneGranted($revision->getContentType()->getFieldType()->getFieldsRoles(), $super)) {
            throw new PrivilegeException($revision);
        }
        //TODO: test circles


        $this->revRepository->lockRevision($revision->getId(), $lockerUsername, new \DateTime($this->lockTime));

        $revision->setLockBy($lockerUsername);
        if ($username) {
            //lock by a console script
            $revision->setLockUntil(new \DateTime("+30 seconds"));
        } else {
            $revision->setLockUntil(new \DateTime($this->lockTime));
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
     */
    public function getRevisionByEnvironment($ouuid, ContentType $contentType, Environment $environment)
    {
        return $this->revRepository->findByEnvironment($ouuid, $contentType, $environment);
    }

    public function propagateDataToComputedField(FormInterface $form, array& $objectArray, ContentType $contentType, $type, $ouuid, $migration = false)
    {
        return $this->propagateDataToComputedFieldRecursive($form, $objectArray, $contentType, $type, $ouuid, $migration, $objectArray, '');
    }

    private function propagateDataToComputedFieldRecursive(FormInterface $form, array& $objectArray, ContentType $contentType, $type, $ouuid, $migration, &$parent, $path)
    {
        $found = false;
        /** @var DataField $dataField*/
        $dataField = $form->getNormData();

        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $form->getConfig()->getType()->getInnerType();

        $options = $dataField->getFieldType()->getOptions();

        if (!$dataFieldType::isVirtual(!$options?[]:$options)) {
            $path .= ($path == ''?'':'.').$form->getConfig()->getName();
        }

        if ($dataField !== null) {
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
                    ]);
                    $out = trim($out);

                    if (strlen($out) > 0) {
                        $json = json_decode($out, true);
                        $meg = json_last_error_msg();
                        if (strcasecmp($meg, 'No error') == 0) {
                            $objectArray[$dataField->getFieldType()->getName()] = $json;
                            $found = true;
                        } else {
                            $this->session->getFlashBag()->add('warning', 'Error to JSON parse the result of the post processing script of field '.$dataField->getFieldType()->getName().' (|json_encode|raw): '.$out);
                        }
                    }
                } catch (\Exception $e) {
                    if ($e->getPrevious() && $e->getPrevious() instanceof CantBeFinalizedException) {
                        if (!$migration) {
                            throw $e->getPrevious();
                        }
                    } else {
                        $this->session->getFlashBag()->add('warning', 'Error to parse the post processing script of field '.$dataField->getFieldType()->getName().': '.$e->getMessage());
                    }
                }
            }
            if ($form->getConfig()->getType()->getInnerType() instanceof ComputedFieldType) {
                $template = $dataField->getFieldType()->getDisplayOptions()['valueTemplate'];

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
                        ]);

                        if ($dataField->getFieldType()->getDisplayOptions()['json']) {
                            $out = json_decode($out, true);
                        } else {
                            $out = trim($out);
                        }
                    } catch (\Exception $e) {
                        if ($e->getPrevious() && $e->getPrevious() instanceof CantBeFinalizedException) {
                            throw $e->getPrevious();
                        }
                        $this->session->getFlashBag()->add('warning', 'Error to parse the computed field '.$dataField->getFieldType()->getName().': '.$e->getMessage());
                    }
                }
                if ($out !== null && $out !== false && (!is_array($out) || !empty($out))) {
                    $objectArray[$dataField->getFieldType()->getName()] = $out;
                } else if (isset($objectArray[$dataField->getFieldType()->getName()])) {
                    unset($objectArray[$dataField->getFieldType()->getName()]);
                }
                $found = true;
            }
        } else {
            //$this->session->getFlashBag()->add('warning', 'Error to parse the post processing script of field '.$dataField->getFieldType()->getName().': ');
        }

        if ($dataFieldType->isContainer() && $form instanceof \IteratorAggregate) {
            foreach ($form->getIterator() as $child) {

               /**@var DataFieldType $childType */
                $childType = $child->getConfig()->getType()->getInnerType();

                if ($childType instanceof CollectionFieldType) {
                    $fieldName = $child->getNormData()->getFieldType()->getName();

                    foreach ($child->getIterator() as $collectionChild) {
                        if (isset($objectArray[$fieldName])) {
                            foreach ($objectArray[$fieldName] as &$elementsArray) {
                                $found = $this->propagateDataToComputedFieldRecursive($collectionChild, $elementsArray, $contentType, $type, $ouuid, $migration, $parent, $path.($path == ''?'':'.').$fieldName) || $found;
                            }
                        }
                    }
                } elseif ($childType instanceof DataFieldType) {
                    $found = $this->propagateDataToComputedFieldRecursive($child, $objectArray, $contentType, $type, $ouuid, $migration, $parent, $path) || $found;
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
            $dataFieldType->convertInput($dataField);
        }
    }

    public function generateInputValues(DataField $dataField)
    {

        foreach ($dataField->getChildren() as $child) {
            $this->generateInputValues($child);
        }
        if (!empty($dataField->getFieldType()) && !empty($dataField->getFieldType()->getType())) {
            /**@var DataFieldType $dataFieldType*/
            $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
            $dataFieldType->generateInput($dataField);
        }
    }

    public function createData($ouuid, array $rawdata, ContentType $contentType, $byARealUser = true)
    {

        $now = new \DateTime();
        $until = $now->add(new \DateInterval($byARealUser?"PT5M":"PT1M"));//+5 minutes
        $newRevision = new Revision();
        $newRevision->setContentType($contentType);
        $newRevision->setOuuid($ouuid);
        $newRevision->setStartTime($now);
        $newRevision->setEndTime(null);
        $newRevision->setDeleted(0);
        $newRevision->setDraft(1);
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
                throw new ConflictHttpException('Duplicate OUUID '.$ouuid.' for this content type');
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

        $objectArray['_contenttype'] = $revision->getContentType()->getName();
        if (isset($objectArray[Mapping::HASH_FIELD])) {
            unset($objectArray[Mapping::HASH_FIELD]);
        }
        if (isset($objectArray[Mapping::SIGNATURE_FIELD])) {
            unset($objectArray[Mapping::SIGNATURE_FIELD]);
        }
        ArrayTool::normalizeArray($objectArray);
        $json = json_encode($objectArray);

        $revision->setSha1(sha1($json));
        $objectArray[Mapping::HASH_FIELD] = $revision->getSha1();

        if (!$silentPublish && $this->private_key) {
            $signature = null;
            if (openssl_sign($json, $signature, $this->private_key, OPENSSL_ALGO_SHA1)) {
                $objectArray[Mapping::SIGNATURE_FIELD] = base64_encode($signature);
            } else {
                $this->session->getFlashBag()->add('warning', 'elasticms was not able to sign the revision\'s data');
            }
        }

        $revision->setRawData($objectArray);

        return $objectArray;
    }

    public function getPublicKey()
    {
        if ($this->private_key && empty($this->public_key)) {
            $certificate= openssl_pkey_get_private($this->private_key);
            $details = openssl_pkey_get_details($certificate);
            $this->public_key =$details['key'];
        }
        return $this->public_key;
    }

    public function getCertificateInfo()
    {
        if ($this->private_key) {
            $certificate= openssl_pkey_get_private($this->private_key);
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
                        $this->session->getFlashBag()->add('warning', 'Sha1 mismatch in '.$environment->getName().' for '.$revision->getContentType()->getName().':'.$revision->getOuuid());
                    }
                    unset($indexedItem[Mapping::HASH_FIELD]);

                    if (isset($indexedItem[Mapping::SIGNATURE_FIELD])) {
                        $binary_signature= base64_decode($indexedItem[Mapping::SIGNATURE_FIELD]);
                        unset($indexedItem[Mapping::SIGNATURE_FIELD]);
                        $data = json_encode($indexedItem);

                        // Check signature
                        $ok = openssl_verify($data, $binary_signature, $this->getPublicKey(), self::ALGO);
                        if ($ok == 1) {
                            //echo "signature ok (as it should be)\n";
                        } elseif ($ok == 0) {
                            $this->session->getFlashBag()->add('warning', 'Data migth be corrupted in '.$environment->getName().' for '.$revision->getContentType()->getName().':'.$revision->getOuuid());
//                                 echo "bad (there's something wrong)\n";
                        } else {
                            $this->session->getFlashBag()->add('warning', 'Error checking signature in '.$environment->getName().' for '.$revision->getContentType()->getName().':'.$revision->getOuuid());
                            // echo "ugly, error checking signature\n";
                        }
                    } else {
                        $data = json_encode($indexedItem);
                        if ($this->private_key) {
                            $this->session->getFlashBag()->add('warning', 'Revision not signed in '.$environment->getName().' for '.$revision->getContentType()->getName().':'.$revision->getOuuid());
                        }
                    }

                    if (sha1($data) != $revision->getSha1()) {
                        $this->session->getFlashBag()->add('warning', 'Computed sha1 mismatch in '.$environment->getName().' for '.$revision->getContentType()->getName().':'.$revision->getOuuid());
                    }
                } else {
                    $this->session->getFlashBag()->add('warning', 'Sha1 not defined in '.$environment->getName().' for '.$revision->getContentType()->getName().':'.$revision->getOuuid());
                }
            } catch (\Exception $e) {
                $this->session->getFlashBag()->add('warning', 'Issue with content indexed in '.$environment->getName().':'.$e->getMessage().' for '.$revision->getContentType()->getName().':'.$revision->getOuuid());
            }
        }
    }

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
     * @param \Symfony\Component\Form\Form $form
     * @param string $username
     * @param boolean $computeFields (allow to sky computedFields compute, i.e during a post-finalize)
     * @throws \Exception
     * @throws DataStateException
     * @return \EMS\CoreBundle\Entity\Revision
     */
    public function finalizeDraft(Revision $revision, Form &$form = null, $username = null, $computeFields = true)
    {
        if ($revision->getDeleted()) {
            throw new \Exception("Can not finalized a deleted revision");
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
        $this->lockRevision($revision, false, false, $username);


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

        if (empty($form) || $this->isValid($form)) {
            $objectArray[Mapping::PUBLISHED_DATETIME_FIELD] = (new \DateTime())->format(\DateTime::ISO8601);

            $config = [
              'index' => $this->contentTypeService->getIndex($revision->getContentType()),
              'type' => $revision->getContentType()->getName(),
              'body' => $objectArray,
            ];

            if ($revision->getContentType()->getHavePipelines()) {
                $config['pipeline'] = $this->instanceId.$revision->getContentType()->getName();
            }

            if (empty($revision->getOuuid())) {
                $status = $this->client->index($config);
                $revision->setOuuid($status['_id']);
            } else {
                $config['id'] = $revision->getOuuid();
                $status = $this->client->index($config);

                $item = $repository->findByOuuidContentTypeAndEnvironnement($revision);
                if ($item) {
                    $this->lockRevision($item, false, false, $username);
                    $previousObjectArray = $item->getRawData();
                    $item->removeEnvironment($revision->getContentType()->getEnvironment());
                    $em->persist($item);
                    $this->unlockRevision($item, $username);
                }
            }

            $revision->addEnvironment($revision->getContentType()->getEnvironment());
//             $revision->getDataField()->propagateOuuid($revision->getOuuid());
            $revision->setDraft(false);

            $revision->setFinalizedBy($username);

            $em->persist($revision);
            $em->flush();


            $this->unlockRevision($revision, $username);
            $this->dispatcher->dispatch(RevisionFinalizeDraftEvent::NAME, new RevisionFinalizeDraftEvent($revision));


            $this->logger->addNotice('log.data.revision.finalized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $revision->getContentType()->getEnvironment()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            ]);

            try {
                $this->postFinalizeTreatment($revision->getContentType()->getName(), $revision->getOuuid(), $form->get('data'), $previousObjectArray);
            } catch (\Exception $e) {
                $this->session->getFlashBag()->add('warning', 'Error while finalize post processing of '.$revision.': '.$e->getMessage());
            }
        } else {
            $form->addError(new FormError("This Form is not valid!"));
            $this->session->getFlashBag()->add('error', 'The revision ' . $revision . ' can not be finalized');
        }
        return $revision;
    }


    /**
     * Parcours all fields and call DataFieldsType postFinalizeTreament function
     *
     * @param string $type
     * @param string $id
     * @param Form $form
     * @param array|null $previousObjectArray
     */
    public function postFinalizeTreatment($type, $id, Form $form, $previousObjectArray)
    {
        /** @var Form $subForm */
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
     * @throws NotFoundHttpException
     * @throws \Exception
     * @return \EMS\CoreBundle\Entity\Revision
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
            if (null == $revisions[0]->getEndTime()) {
                $revision = $revisions[0];
                return $revision;
            } else {
                throw new NotFoundHttpException('Revision for ouuid '.$ouuid.' and contenttype '.$type.' with end time '.$revisions[0]->getEndTime());
            }
        } elseif (count($revisions) == 0) {
            throw new NotFoundHttpException('Revision not found for ouuid '.$ouuid.' and contenttype '.$type);
        } else {
            throw new \Exception('Too much newest revisions available for ouuid '.$ouuid.' and contenttype '.$type);
        }
    }

    public function newDocument(ContentType $contentType, ?string $ouuid = null, ?array $rawData = null)
    {
        $this->hasCreateRights($contentType);
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
                    $this->session->getFlashBag()->add('error', 'elasticms was not able to initiate the default value (json_decode), please check the content type\'s configuration');
                } else {
                    $revision->setRawData($raw);
                }
            } catch (\Twig_Error $e) {
                $this->session->getFlashBag()->add('error', 'elasticms was not able to initiate the default value (twig error), please check the content type\'s configuration');
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


        $now = new \DateTime('now');
        $revision->setContentType($contentType);
        $revision->setDraft(true);
        $revision->setOuuid($ouuid);
        $revision->setDeleted(false);
        $revision->setStartTime($now);
        $revision->setEndTime(null);
        $revision->setLockBy($this->userService->getCurrentUser()->getUsername());
        $revision->setLockUntil(new \DateTime($this->lockTime));

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
            $revision->setCircles(is_array($objectArray[$revision->getContentType()->getCirclesField()])?$objectArray[$revision->getContentType()->getCirclesField()]:[$objectArray[$revision->getContentType()->getCirclesField()]]);
        } else {
            $revision->setCircles(null);
        }
    }

    private function setLabelField(Revision $revision)
    {
//setMetaField
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

    public function initNewDraft($type, $ouuid, $fromRev = null, $username = null)
    {

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var ContentTypeRepository $contentTypeRepo */
        $contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');
        /** @var ContentType $contentType */
        $contentType = $contentTypeRepo->findOneBy([
                'name' => $type,
                'deleted' => false,
        ]);

        if (!$contentType) {
            throw new NotFoundHttpException('ContentType '.$type.' Not found');
        }


        $revision = $this->getNewestRevision($type, $ouuid);
        $revision->setDeleted(false);
        if (null !== $revision->getDataField()) {
            $revision->getDataField()->propagateOuuid($revision->getOuuid());
        }


         $this->setMetaFields($revision);

        $this->lockRevision($revision, false, false, $username);



        if (! $revision->getDraft()) {
            $now = new \DateTime();

            if ($fromRev) {
                $newDraft = new Revision($fromRev);
            } else {
                $newDraft = new Revision($revision);
            }

            $newDraft->setStartTime($now);
            $revision->setEndTime($now);

            $this->lockRevision($newDraft, false, false, $username);

            $em->persist($revision);
            $em->persist($newDraft);
            $em->flush();

            $this->dispatcher->dispatch(RevisionNewDraftEvent::NAME, new RevisionNewDraftEvent($newDraft));

            return $newDraft;
        }
        return $revision;
    }

    public function discardDraft(Revision $revision)
    {
        $this->lockRevision($revision);

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');

        if (!$revision) {
            throw new NotFoundHttpException('Revision not found');
        }
        if (!$revision->getDraft() || null != $revision->getEndTime()) {
            throw new BadRequestHttpException('Only authorized on a draft');
        }

        $contentTypeId = $revision->getContentType()->getId();

        $hasPreviousRevision = false;

        if (null != $revision->getOuuid()) {
            /** @var QueryBuilder $qb */
            $qb = $repository->createQueryBuilder('t')
            ->where('t.ouuid = :ouuid')
            ->andWhere('t.id <> :id')
//             ->andWhere('t.deleted =  :false')
            ->andWhere('t.contentType =  :contentType')
            ->orderBy('t.id', 'desc')
            ->setParameter('ouuid', $revision->getOuuid())
            ->setParameter('contentType', $revision->getContentType())
            ->setParameter('id', $revision->getId())
//             ->setParameter('false', false)
            ->setMaxResults(1);
            $query = $qb->getQuery();


            $result = $query->getResult();

            if (count($result) == 1) {
                /** @var Revision $previous */
                $previous = $result[0];
                $this->lockRevision($previous);
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
            $this->lockRevision($revision, true);

            /** @var Environment $environment */
            foreach ($revision->getEnvironments() as $environment) {
                try {
                    $this->client->delete([
                            'index' => $this->contentTypeService->getIndex($revision->getContentType()),
                            'type' => $revision->getContentType()->getName(),
                            'id' => $revision->getOuuid(),
                    ]);
                    $this->session->getFlashBag()->add('notice', 'The object has been unpublished from environment '.$environment->getName());
                } catch (Missing404Exception $e) {
                    if (!$revision->getDeleted()) {
                        $this->session->getFlashBag()->add('warning', 'The object was already removed from environment '.$environment->getName());
                    }
                    throw $e;
                }
                $revision->removeEnvironment($environment);
            }
            $revision->setDeleted(true);
            $revision->setDeletedBy($this->tokenStorage->getToken()->getUsername());
            $em->persist($revision);
        }
        $this->session->getFlashBag()->add('notice', 'The object have been marked as deleted! ');
        $em->flush();
    }

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
            $this->lockRevision($revision, true);
            $em->remove($revision);
        }
        $em->flush();
    }

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
            $this->lockRevision($revision, true);
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


    public function updateDataStructure(FieldType $meta, DataField $dataField)
    {

        //no need to generate the structure for subfields (
        $isContainer = true;

        if (null !== $dataField->getFieldType()) {
//             $type = $dataField->getFieldType()->getType();
            $datFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
            $isContainer = $datFieldType->isContainer();
        }

        if ($isContainer) {
            /** @var FieldType $field */
            foreach ($meta->getChildren() as $field) {
                //no need to generate the structure for delete field
                if (!$field->getDeleted()) {
                    $child = $dataField->__get('ems_'.$field->getName());
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
    public function updateDataValue(DataField $dataField, Array &$elasticIndexDatas, $isMigration = false)
    {
        $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();

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
    }

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
            $this->session->getFlashBag()->add('warning', "Some data of this revision were not consumed by the content type:".$html);
        }
    }

    public function reloadData(Revision $revision, $migration = true)
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
        if ($finalizationDate!== false) {
            $objectArray[Mapping::FINALIZATION_DATETIME_FIELD] = $finalizationDate;
        }

        $revision->setRawData($objectArray);
        return $objectArray;
    }



    public function getSubmitData(Form $form)
    {
        $out = $form->getViewData();
        /**@var Form $subform*/
        foreach ($form->getIterator() as $subform) {
            if ($subform->getConfig()->getCompound()) {
                $out[$subform->getName()] = $this->getSubmitData($subform);
            }
        }
        return $out;
    }

    /**
     *
     * @return \EMS\CoreBundle\Entity\Revision
     */
    public function getEmptyRevision(ContentType $contentType, $user)
    {
        $newRevision= new Revision();

        $now = new \DateTime();
        $until = $now->add(new \DateInterval("PT5M"));//+5 minutes
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
            if (is_array($item)) {
                $out .= DataService::arrayToHtml($item);
            } else {
                $out .= $item;
            }
            $out .= '</li>';
        }
        return $out.'</ul>';
    }

    public function isValid(\Symfony\Component\Form\Form &$form, DataField $parent = null, &$masterRawData = null)
    {
        if ($form->getName() == '_ems_internal_deleted' && $parent != null && $parent->getFieldType() != null && $parent->getFieldType()->getType() == CollectionItemFieldType::class) {
            return true;
        }

        $viewData = $form->getNormData();

        //pour le champ hidden allFieldsAreThere de Revision
        if (!is_object($viewData) && 'allFieldsAreThere' == $form->getName()) {
            return true;
        }

        if ($viewData instanceof Revision) {
            $viewData = $form->get('data')->getNormData();

            $masterRawData = $viewData->getRawData();
        }

        if ($viewData instanceof DataField) {
            /** @var DataField $dataField */
            $dataField = $viewData;
        } else {
            throw new \Exception("Unforeseen type of viewData");
        }

        if ($dataField->getFieldType() !== null && $dataField->getFieldType()->getType() !== null) {
//             $dataFieldTypeClassName = $dataField->getFieldType()->getType();
//             /** @var DataFieldType $dataFieldType */
//             $dataFieldType = new $dataFieldTypeClassName();
            /** @var DataFieldType $dataFieldType */
            $dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
            $dataFieldType->isValid($dataField, $parent, $masterRawData);
        }
        $isValid = true;
        if ($dataFieldType !== null && $dataFieldType->isContainer()) {//If datafield is container or type is null => Container => Recursive
            $formChildren = $form->all();
            foreach ($formChildren as $child) {
                if ($child instanceof \Symfony\Component\Form\Form) {
                    $tempIsValid = $this->isValid($child, $dataField, $masterRawData);//Recursive
                    $isValid = $isValid && $tempIsValid;
                }
            }
            if (!$isValid) {
                $form->addError(new FormError("At least one field is not valid!"));
            }
        }
//           $isValid = $isValid && $dataFieldType->isValid($dataField);
        if ($dataFieldType !== null && !$dataFieldType->isValid($dataField, $parent)) {
            $isValid = false;
            $form->addError(new FormError("This Field is not valid! ".$dataField->getMessages()[0]));
        }

        if ($form->getErrors(true, true)->count() > 0) {
            $isValid = false;
        }

        return $isValid;
    }

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
            if (null == $revisions[0]->getEndTime()) {
                $revision = $revisions[0];
                return $revision;
            } else {
                throw new \Exception('Revision for ouuid '.$id.' and contenttype '.$type.' with end time '.$revisions[0]->getEndTime());
            }
        } elseif (count($revisions) == 0) {
            throw new NotFoundHttpException('Revision not found for id '.$id.' and contenttype '.$type);
        } else {
            throw new \Exception('Too much newest revisions available for ouuid '.$id.' and contenttype '.$type);
        }
    }

    /**
     *
     * @param Revision $revision
     * @param array $rawData
     * @param string $replaceOrMerge
     * @return \EMS\CoreBundle\Entity\Revision
     */
    public function replaceData(Revision $revision, array $rawData, $replaceOrMerge = "replace")
    {

        if (! $revision->getDraft()) {
            $em = $this->doctrine->getManager();
            $this->lockRevision($revision, false, false);

            $now = new \DateTime();

            $newDraft = new Revision($revision);

            if ($replaceOrMerge === "replace") {
                $newDraft->setRawData($rawData);
            } elseif ($replaceOrMerge === "merge") {
                $newRawData = array_merge($revision->getRawData(), $rawData);
                $newDraft->setRawData($newRawData);
            } else {
                $this->session->getFlashBag()->add('error', 'The revision ' . $revision . ' has not been replaced or replaced');
                return $revision;
            }

            $newDraft->setStartTime($now);
            $revision->setEndTime($now);

            $this->lockRevision($newDraft, false, false);

            $em->persist($revision);
            $em->persist($newDraft);
            $em->flush();
            return $newDraft;
        } else {
            $this->session->getFlashBag()->add('error', 'The revision ' . $revision . ' is not a finalize version');
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
     *
     * @param UpdateRevisionReferersEvent $event
     */
    public function updateReferers(UpdateRevisionReferersEvent $event)
    {

        $form = null;
        foreach ($event->getToCleanOuuids() as $ouuid) {
            try {
                $key = explode(':', $ouuid);
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
                $this->session->getFlashBag()->add('error', 'elasticms was not able to udate referers of object ' . $ouuid . ':' . $e->getMessage());
            }
        }


        foreach ($event->getToCreateOuuids() as $ouuid) {
            try {
                $key = explode(':', $ouuid);
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
                $this->session->getFlashBag()->add('error', 'elasticms was not able to udate referers of object ' . $ouuid . ':' . $e->getMessage());
            }
        }
    }
}
