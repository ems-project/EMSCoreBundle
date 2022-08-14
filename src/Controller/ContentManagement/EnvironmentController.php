<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use EMS\CommonBundle\Common\Standard\Type;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\RebuildIndex;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\CompareEnvironmentFormType;
use EMS\CoreBundle\Form\Form\EditEnvironmentType;
use EMS\CoreBundle\Form\Form\RebuildIndexType;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\Mapping;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\SearchService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class EnvironmentController extends AbstractController
{
    private SearchService $searchService;
    private EnvironmentService $environmentService;
    private ContentTypeService $contentTypeService;
    private AuthorizationCheckerInterface $authorizationChecker;
    private PublishService $publishService;
    private LoggerInterface $logger;
    private IndexService $indexService;
    private Mapping $mapping;
    private AliasService $aliasService;
    private JobService $jobService;
    private int $pagingSize;
    private string $instanceId;
    private ?string $circlesObject;

    public function __construct(
        LoggerInterface $logger,
        SearchService $searchService,
        EnvironmentService $environmentService,
        ContentTypeService $contentTypeService,
        AuthorizationCheckerInterface $authorizationChecker,
        PublishService $publishService,
        IndexService $indexService,
        Mapping $mapping,
        AliasService $aliasService,
        JobService $jobService,
        int $pagingSize,
        string $instanceId,
        ?string $circlesObject)
    {
        $this->logger = $logger;
        $this->searchService = $searchService;
        $this->environmentService = $environmentService;
        $this->contentTypeService = $contentTypeService;
        $this->authorizationChecker = $authorizationChecker;
        $this->publishService = $publishService;
        $this->indexService = $indexService;
        $this->mapping = $mapping;
        $this->aliasService = $aliasService;
        $this->jobService = $jobService;
        $this->pagingSize = $pagingSize;
        $this->instanceId = $instanceId;
        $this->circlesObject = $circlesObject;
    }

    public function alignAction(Request $request): Response
    {
        if (!$this->isGranted(['ROLE_PUBLISHER'])) {
            throw new AccessDeniedHttpException();
        }
        $data = [];
        $env = [];
        $withEnvi = [];

        $form = $this->createForm(CompareEnvironmentFormType::class, $data, [
        ]);

        $form->handleRequest($request);
        $paging_size = $this->pagingSize;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if ($data['environment'] == $data['withEnvironment']) {
                $form->addError(new FormError('Source and target environments must be different'));
            } else {
                if (\array_key_exists('alignWith', $request->request->get('compare_environment_form'))) {
                    $alignTo = [];
                    $alignTo[$request->query->get('withEnvironment')] = $request->query->get('withEnvironment');
                    $alignTo[$request->query->get('environment')] = $request->query->get('environment');
                    $revid = $request->request->get('compare_environment_form')['alignWith'];

                    /** @var EntityManager $em */
                    $em = $this->getDoctrine()->getManager();

                    $repository = $em->getRepository(Revision::class);

                    /** @var Revision $revision */
                    $revision = $repository->findOneBy([
                            'id' => $revid,
                    ]);

                    foreach ($revision->getEnvironments() as $item) {
                        if (\array_key_exists($item->getName(), $alignTo)) {
                            unset($alignTo[$item->getName()]);
                        }
                    }

                    $continue = true;
                    foreach ($alignTo as $env) {
                        if ($revision->giveContentType()->giveEnvironment()->getName() == $env) {
                            $this->logger->warning('log.environment.cant_align_default_environment', [
                                EmsFields::LOG_ENVIRONMENT_FIELD => $env,
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType(),
                                EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            ]);
                            $continue = false;
                            break;
                        }

                        if (!$this->authorizationChecker->isGranted($revision->giveContentType()->getPublishRole())) {
                            $this->logger->warning('log.environment.dont_have_publish_role', [
                                EmsFields::LOG_ENVIRONMENT_FIELD => $env,
                                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType(),
                                EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
                                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            ]);
                            $continue = false;
                            break;
                        }
                    }

                    if ($continue) {
                        foreach ($alignTo as $env) {
                            $firstEnvironment = $revision->getEnvironments()->first();
                            if (false !== $firstEnvironment) {
                                $this->publishService->alignRevision($revision->giveContentType()->getName(), $revision->giveOuuid(), $firstEnvironment->getName(), $env);
                            }
                        }
                    }
                } elseif (\array_key_exists('alignLeft', $request->request->get('compare_environment_form'))) {
                    foreach ($request->request->get('compare_environment_form')['item_to_align'] as $item) {
                        $exploded = \explode(':', $item);
                        if (2 == \count($exploded)) {
                            $this->publishService->alignRevision($exploded[0], $exploded[1], $request->query->get('withEnvironment'), $request->query->get('environment'));
                        } else {
                            $this->logger->warning('log.environment.wrong_ouuid', [
                                EmsFields::LOG_OUUID_FIELD => $item,
                            ]);
                        }
                    }
                } elseif (\array_key_exists('alignRight', $request->request->get('compare_environment_form'))) {
                    foreach ($request->request->get('compare_environment_form')['item_to_align'] as $item) {
                        $exploded = \explode(':', $item);
                        if (2 == \count($exploded)) {
                            $this->publishService->alignRevision($exploded[0], $exploded[1], $request->query->get('environment'), $request->query->get('withEnvironment'));
                        } else {
                            $this->logger->warning('log.environment.wrong_ouuid', [
                                EmsFields::LOG_OUUID_FIELD => $item,
                            ]);
                        }
                    }
                } elseif (\array_key_exists('compare', $request->request->get('compare_environment_form'))) {
                    $request->query->set('environment', $data['environment']);
                    $request->query->set('withEnvironment', $data['withEnvironment']);
                    $request->query->set('contentTypes', $data['contentTypes']);
                    $request->query->set('page', 1);
                }

                return $this->redirectToRoute('environment.align', $request->query->all());
            }
        }

        if (null != $request->query->get('page')) {
            $page = $request->query->get('page');
        } else {
            $page = 1;
        }

        $contentTypes = $request->query->get('contentTypes', []);
        if (!$form->isSubmitted()) {
            $form->get('contentTypes')->setData($contentTypes);
        }
        if (empty($contentTypes)) {
            $contentTypes = $form->get('contentTypes')->getConfig()->getOption('choices', []);
        }

        $orderField = $request->query->get('orderField', 'contenttype');
        $orderDirection = $request->query->get('orderDirection', 'asc');

        if (null != $request->query->get('environment')) {
            $environment = $request->query->get('environment');
            if (!$form->isSubmitted()) {
                $form->get('environment')->setData($environment);
            }
        } else {
            $environment = false;
        }

        if (null != $request->query->get('withEnvironment')) {
            $withEnvironment = $request->query->get('withEnvironment');

            if (!$form->isSubmitted()) {
                $form->get('withEnvironment')->setData($withEnvironment);
            }
        } else {
            $withEnvironment = false;
        }

        if ($environment && $withEnvironment) {
            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            /** @var RevisionRepository $repository */
            $repository = $em->getRepository(Revision::class);

            $env = $this->environmentService->giveByName($environment);
            $withEnvi = $this->environmentService->giveByName($withEnvironment);

            $total = $repository->countDifferencesBetweenEnvironment($env->getId(), $withEnvi->getId(), $contentTypes);
            if ($total) {
                $lastPage = \ceil($total / $paging_size);
                if ($page > $lastPage) {
                    $page = $lastPage;
                }
                $results = $repository->compareEnvironment(
                    $env->getId(),
                    $withEnvi->getId(),
                    $contentTypes,
                    ($page - 1) * $paging_size,
                    $paging_size,
                    $orderField,
                    $orderDirection
                );
                for ($index = 0; $index < \count($results); ++$index) {
                    $results[$index]['contentType'] = $this->contentTypeService->getByName($results[$index]['content_type_name']);
//                     $results[$index]['revisionEnvironment'] = $repository->findOneById($results[$index]['rId']);
//TODO: is it the better options? to concatenate and split things?
                    $minrevid = \explode('/', $results[$index]['minrevid']); //1/81522/2017-03-08 14:32:52 => e.id/r.id/r.created
                    $maxrevid = \explode('/', $results[$index]['maxrevid']);

                    $results[$index]['revisionEnvironment'] = $repository->findOneById((int) $minrevid[1]);
                    $results[$index]['revisionWithEnvironment'] = $repository->findOneById((int) $maxrevid[1]);

                    $contentType = $results[$index]['contentType'];
                    if (false === $contentType) {
                        throw new \RuntimeException(\sprintf('Content type %s not found', $results[$index]['contentType']));
                    }
                    try {
                        $document = $this->searchService->getDocument($contentType, $results[$index]['ouuid'], $env);
                        $results[$index]['objectEnvironment'] = $document->getRaw();
                    } catch (NotFoundException $e) {
                        $results[$index]['objectEnvironment'] = null; //This revision doesn't exist in this environment, but it's ok.
                    }
                    try {
                        $document = $this->searchService->getDocument($contentType, $results[$index]['ouuid'], $withEnvi);
                        $results[$index]['objectWithEnvironment'] = $document->getRaw();
                    } catch (NotFoundException $e) {
                        $results[$index]['objectWithEnvironment'] = null; //This revision doesn't exist in this environment, but it's ok.
                    }
                }
            } else {
                $page = $lastPage = 1;
                $this->logger->notice('log.environment.aligned', [
                    EmsFields::LOG_ENVIRONMENT_FIELD => $environment,
                    'with_environment' => $withEnvironment,
                ]);
                $total = 0;
                $results = [];
            }
        } else {
            $environment = false;
            $withEnvironment = false;
            $results = false;
            $page = 0;
            $total = 0;
            $lastPage = 0;
        }

        return $this->render('@EMSCore/environment/align.html.twig', [
            'form' => $form->createView(),
            'results' => $results,
            'lastPage' => $lastPage,
            'paginationPath' => 'environment.align',
            'page' => $page,
            'paging_size' => $paging_size,
            'total' => $total,
            'currentFilters' => $request->query,
            'fromEnv' => $env,
            'withEnv' => $withEnvi,
            'environment' => $environment,
            'withEnvironment' => $withEnvironment,
            'environments' => $this->environmentService->getAll(),
            'orderField' => $orderField,
            'orderDirection' => $orderDirection,
            'contentTypes' => $this->contentTypeService->getAll(),
         ]);
    }

    /**
     * @param string $name
     */
    public function attachAction($name): Response
    {
        try {
            if ($this->indexService->hasIndex($name)) {
                /** @var EntityManager $em */
                $em = $this->getDoctrine()->getManager();

                $environmentRepository = $em->getRepository(Environment::class);
                $anotherObject = $environmentRepository->findBy([
                        'name' => $name,
                ]);

                if (0 == \count($anotherObject)) {
                    $environment = new Environment();
                    $environment->setName($name);
                    $environment->setAlias($name);
                    //TODO: setCircles
                    $environment->setManaged(false);

                    $em->persist($environment);
                    $em->flush();

                    $this->logger->notice('log.environment.alias_attached', [
                        'alias' => $name,
                    ]);

                    return $this->redirectToRoute('environment.edit', [
                            'id' => $environment->getId(),
                    ]);
                }
            }
        } catch (NotFoundException $e) {
            $this->logger->error('log.error', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);
        }

        return $this->redirectToRoute('environment.index');
    }

    /**
     * @param string $name
     */
    public function removeAliasAction($name): Response
    {
        if ($this->aliasService->removeAlias($name)) {
            $this->logger->notice('log.environment.alias_removed', [
                'alias' => $name,
            ]);
        }

        return $this->redirectToRoute('environment.index');
    }

    public function removeAction(int $id): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var EnvironmentRepository $repository */
        $repository = $em->getRepository(Environment::class);
        /** @var Environment $environment */
        $environment = $repository->find($id);

        if (0 !== $environment->getRevisions()->count()) {
            $this->logger->error('log.environment.not_empty', [
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
            ]);

            return $this->redirectToRoute('environment.index');
        }

        if ($environment->getManaged()) {
            $indexes = $this->indexService->getIndexesByAlias($environment->getAlias());
            if (empty($indexes)) {
                $this->logger->warning('log.environment.alias_not_found', [
                    'alias' => $environment->getAlias(),
                ]);
            }
            foreach ($indexes as $index) {
                $this->indexService->deleteIndex($index);
            }
        }

        $linked = false;
        /** @var ContentType $contentType */
        foreach ($environment->getContentTypesHavingThisAsDefault() as $contentType) {
            if (!$contentType->getDeleted()) {
                $linked = true;
                break;
            }
        }

        if ($linked) {
            $this->logger->error('log.environment.is_default', [
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
            ]);
        } else {
            /** @var ContentType $contentType */
            foreach ($environment->getContentTypesHavingThisAsDefault() as $contentType) {
                $contentType->getFieldType()->setContentType(null);
                $em->persist($contentType->getFieldType());
                $em->flush();
                $em->remove($contentType);
                $em->flush();
            }
            $em->remove($environment);
            $em->flush();
            $this->logger->notice('log.environment.deleted', [
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
            ]);
        }

        return $this->redirectToRoute('environment.index');
    }

    public static function isValidName(string $name): bool
    {
        return \preg_match('/^[a-z][a-z0-9\-_]*$/', $name) && \strlen($name) <= 100;
    }

    public function addAction(Request $request): Response
    {
        $environment = new Environment();

        $form = $this->createFormBuilder($environment)->add('name', IconTextType::class, [
                'icon' => 'fa fa-database',
                'required' => false,
        ])->add('color', ColorPickerType::class, [
                'required' => false,
        ])->add('save', SubmitEmsType::class, [
                'label' => 'Create',
                'icon' => 'fa fa-plus',
                'attr' => [
                        'class' => 'btn btn-primary pull-right',
                ],
        ])->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            /** @var Environment $environment */
            $environment = $form->getData();
            if (!$this->isValidName($environment->getName())) {
                $form->get('name')->addError(new FormError('Must respects the following regex /^[a-z][a-z0-9\-_]*$/'));
            }

            if ($form->isValid()) {
                /** @var EntityManager $em */
                $em = $this->getDoctrine()->getManager();

                $environmentRepository = $em->getRepository(Environment::class);
                $anotherObject = $environmentRepository->findBy([
                        'name' => $environment->getName(),
                ]);

                if (0 != \count($anotherObject)) {
                    //TODO: test name format
                    $form->get('name')->addError(new FormError('Another environment named '.$environment->getName().' already exists'));
                } else {
                    $environment->setAlias($this->instanceId.$environment->getName());
                    $environment->setManaged(true);
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($environment);
                    $em->flush();

                    $indexName = $environment->getNewIndexName();
                    $this->mapping->createIndex($indexName, $this->environmentService->getIndexAnalysisConfiguration());

                    foreach ($this->contentTypeService->getAll() as $contentType) {
                        $this->contentTypeService->updateMapping($contentType, $indexName);
                    }

                    $this->indexService->updateAlias($environment->getAlias(), [], [$indexName]);

                    $this->logger->notice('log.environment.created', [
                        EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                    ]);

                    return $this->redirectToRoute('environment.index');
                }
            }
        }

        return $this->render('@EMSCore/environment/add.html.twig', [
                'form' => $form->createView(),
        ]);
    }

    public function editAction(int $id, Request $request): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var EnvironmentRepository $repository */
        $repository = $em->getRepository(Environment::class);

        /** @var Environment|null $environment */
        $environment = $repository->find($id);

        if (null === $environment) {
            throw new NotFoundHttpException('Unknow environment');
        }

        $options = [];
        if (null !== $this->circlesObject && '' !== $this->circlesObject) {
            $options['type'] = $this->circlesObject;
        }

        $form = $this->createForm(EditEnvironmentType::class, $environment, $options);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($environment);
            $em->flush();
            $this->logger->notice('log.environment.updated', [
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
            ]);

            return $this->redirectToRoute('environment.index');
        }

        return $this->render('@EMSCore/environment/edit.html.twig', [
                'environment' => $environment,
                'form' => $form->createView(),
        ]);
    }

    public function viewAction(int $id): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var EnvironmentRepository $repository */
        $repository = $em->getRepository(Environment::class);

        /** @var Environment|null $environment */
        $environment = $repository->find($id);

        if (null === $environment) {
            throw new NotFoundHttpException('Unknow environment');
        }

        try {
            $info = $this->mapping->getMapping([$environment->getName()]);
        } catch (NotFoundException $e) {
            $this->logger->error('log.environment.alias_missing', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                'alias' => $environment->getAlias(),
            ]);
            $info = false;
        }

        return $this->render('@EMSCore/environment/view.html.twig', [
                'environment' => $environment,
                'info' => $info,
        ]);
    }

    public function rebuild(int $id, Request $request): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var EnvironmentRepository $repository */
        $repository = $em->getRepository(Environment::class);

        /** @var Environment|null $environment */
        $environment = $repository->find($id);

        if (null === $environment) {
            throw new NotFoundHttpException('Unknow environment');
        }

        $rebuildIndex = new RebuildIndex();

        $form = $this->createForm(RebuildIndexType::class, $rebuildIndex);

        $form->handleRequest($request);

        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException('Unexpected user object');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $option = $rebuildIndex->getOption();

            switch ($option) {
                case 'newIndex':
                    $job = $this->jobService->createCommand($user, \sprintf('ems:environment:rebuild %s', $environment->getName()));

                    return $this->redirectToRoute('job.status', [
                        'job' => $job->getId(),
                    ]);
                case 'sameIndex':
                    $job = $this->jobService->createCommand($user, \sprintf('ems:environment:reindex %s', $environment->getName()));

                    return $this->redirectToRoute('job.status', [
                        'job' => $job->getId(),
                    ]);
                default:
                    $this->logger->warning('log.environment.rebuild_unknown_option', [
                        EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                        'option' => $option,
                    ]);
            }
        }

        return $this->render('@EMSCore/environment/rebuild.html.twig', [
                'environment' => $environment,
                'form' => $form->createView(),
        ]);
    }

    public function indexAction(Request $request): Response
    {
        try {
            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();

            $logger = $this->logger;
            $logger->debug('For each environments: start');

            $builder = $this->createFormBuilder([])
            ->add('reorder', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary ',
                ],
                'label' => 'controller.environment.index.reorder_submit_button',
                'icon' => 'fa fa-reorder',
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ]);

            $names = [];

            $this->aliasService->build();
            $environments = [];
            $stats = $this->environmentService->getEnvironmentsStats();
            /* @var  Environment $environment */
            foreach ($stats as $stat) {
                $environment = $stat['environment'];
                $environment->setCounter($stat['counter']);
                $environment->setDeletedRevision($stat['deleted']);
                if ($this->aliasService->hasAlias($environment->getAlias())) {
                    $alias = $this->aliasService->getAlias($environment->getAlias());

                    $environment->setIndexes($alias['indexes']);
                    $environment->setTotal($alias['total']);
                }
                $environments[] = $environment;
                $names[] = $environment->getName();
            }
            $logger->debug('For each environments: done');

            $builder->add('environmentNames', CollectionType::class, [
                    // each entry in the array will be an "email" field
                    'entry_type' => HiddenType::class,
                    // these options are passed to each "email" type
                    'entry_options' => [
                    ],
                    'data' => $names,
            ]);

            $form = $builder->getForm();

            if ($request->isMethod('POST')) {
                $form = $request->get('form');
                if (isset($form['environmentNames']) && \is_array($form['environmentNames'])) {
                    $counter = 0;
                    foreach ($form['environmentNames'] as $name) {
                        $contentType = $this->environmentService->getByName($name);
                        if ($contentType) {
                            $contentType->setOrderKey($counter);
                            $em->persist($contentType);
                        }
                        ++$counter;
                    }

                    $em->flush();
                    $this->logger->notice('log.environment.reordered', [
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                    ]);
                }

                return $this->redirectToRoute('ems_environment_index');
            }

            return $this->render('@EMSCore/environment/index.html.twig', [
                'environments' => $environments,
                'orphanIndexes' => $this->aliasService->getOrphanIndexes(),
                'unreferencedAliases' => $this->aliasService->getUnreferencedAliases(),
                'managedAliases' => $this->aliasService->getManagedAliases(),
                'form' => $form->createView(),
            ]);
        } catch (NoNodesAvailableException $e) {
            return $this->redirectToRoute('elasticsearch.status');
        }
    }
}
