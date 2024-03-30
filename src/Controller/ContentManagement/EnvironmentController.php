<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use EMS\CommonBundle\Common\Standard\Type;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\EnvironmentDataTableType;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\RebuildIndex;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\CompareEnvironmentFormType;
use EMS\CoreBundle\Form\Form\EditEnvironmentType;
use EMS\CoreBundle\Form\Form\RebuildIndexType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\FieldTypeRepository;
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
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class EnvironmentController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SearchService $searchService,
        private readonly EnvironmentService $environmentService,
        private readonly ContentTypeService $contentTypeService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly PublishService $publishService,
        private readonly IndexService $indexService,
        private readonly Mapping $mapping,
        private readonly AliasService $aliasService,
        private readonly JobService $jobService,
        private readonly RevisionRepository $revisionRepository,
        private readonly EnvironmentRepository $environmentRepository,
        private readonly FieldTypeRepository $fieldTypeRepository,
        private readonly ContentTypeRepository $contentTypeRepository,
        private readonly int $pagingSize,
        private readonly string $instanceId,
        private readonly ?string $circlesObject,
        private readonly string $templateNamespace,
        private readonly DataTableFactory $dataTableFactory)
    {
    }

    public function alignAction(Request $request): Response
    {
        if (!$this->isGranted('ROLE_PUBLISHER')) {
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
                if (\array_key_exists('alignWith', $request->request->all('compare_environment_form'))) {
                    $alignTo = [];
                    $alignTo[Type::string($request->query->get('withEnvironment'))] = Type::string($request->query->get('withEnvironment'));
                    $alignTo[Type::string($request->query->get('environment'))] = Type::string($request->query->get('environment'));
                    $revid = $request->request->all('compare_environment_form')['alignWith'];
                    /** @var Revision $revision */
                    $revision = $this->revisionRepository->findOneBy([
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

                        if (!$this->authorizationChecker->isGranted($revision->giveContentType()->role(ContentTypeRoles::PUBLISH))) {
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
                } elseif (\array_key_exists('alignLeft', $request->request->all('compare_environment_form'))) {
                    foreach ($request->request->all('compare_environment_form')['item_to_align'] as $item) {
                        $exploded = \explode(':', (string) $item);
                        if (2 == \count($exploded)) {
                            $this->publishService->alignRevision($exploded[0], $exploded[1], Type::string($request->query->get('withEnvironment')), Type::string($request->query->get('environment')));
                        } else {
                            $this->logger->warning('log.environment.wrong_ouuid', [
                                EmsFields::LOG_OUUID_FIELD => $item,
                            ]);
                        }
                    }
                } elseif (\array_key_exists('alignRight', $request->request->all('compare_environment_form'))) {
                    foreach ($request->request->all('compare_environment_form')['item_to_align'] as $item) {
                        $exploded = \explode(':', (string) $item);
                        if (2 == \count($exploded)) {
                            $this->publishService->alignRevision($exploded[0], $exploded[1], Type::string($request->query->get('environment')), Type::string($request->query->get('withEnvironment')));
                        } else {
                            $this->logger->warning('log.environment.wrong_ouuid', [
                                EmsFields::LOG_OUUID_FIELD => $item,
                            ]);
                        }
                    }
                } elseif (\array_key_exists('compare', $request->request->all('compare_environment_form'))) {
                    $request->query->set('environment', $data['environment']);
                    $request->query->set('withEnvironment', $data['withEnvironment']);
                    $request->query->set('contentTypes', $data['contentTypes']);
                    $request->query->set('page', 1);
                }

                return $this->redirectToRoute('environment.align', $request->query->all());
            }
        }

        $page = $request->query->getInt('page', 1);

        $contentTypes = $request->query->all('contentTypes');
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
            $env = $this->environmentService->giveByName($environment);
            $withEnvi = $this->environmentService->giveByName($withEnvironment);

            $total = $this->revisionRepository->countDifferencesBetweenEnvironment($env->getId(), $withEnvi->getId(), $contentTypes);
            if ($total) {
                $lastPage = \ceil($total / $paging_size);
                if ($page > $lastPage) {
                    $page = $lastPage;
                }
                $results = $this->revisionRepository->compareEnvironment(
                    $env->getId(),
                    $withEnvi->getId(),
                    $contentTypes,
                    (int) (($page - 1) * $paging_size),
                    $paging_size,
                    $orderField,
                    $orderDirection
                );
                for ($index = 0; $index < \count($results); ++$index) {
                    $results[$index]['contentType'] = $this->contentTypeService->getByName($results[$index]['content_type_name']);
//                     $results[$index]['revisionEnvironment'] = $repository->findOneById($results[$index]['rId']);
// TODO: is it the better options? to concatenate and split things?
                    $minrevid = \explode('/', (string) $results[$index]['minrevid']); // 1/81522/2017-03-08 14:32:52 => e.id/r.id/r.created
                    $maxrevid = \explode('/', (string) $results[$index]['maxrevid']);

                    $results[$index]['revisionEnvironment'] = $this->revisionRepository->findOneById((int) $minrevid[1]);
                    $results[$index]['revisionWithEnvironment'] = $this->revisionRepository->findOneById((int) $maxrevid[1]);

                    $contentType = $results[$index]['contentType'];
                    if (false === $contentType) {
                        throw new \RuntimeException(\sprintf('Content type %s not found', $results[$index]['contentType']));
                    }
                    try {
                        $document = $this->searchService->getDocument($contentType, $results[$index]['ouuid'], $env);
                        $results[$index]['objectEnvironment'] = $document->getRaw();
                    } catch (NotFoundException) {
                        $results[$index]['objectEnvironment'] = null; // This revision doesn't exist in this environment, but it's ok.
                    }
                    try {
                        $document = $this->searchService->getDocument($contentType, $results[$index]['ouuid'], $withEnvi);
                        $results[$index]['objectWithEnvironment'] = $document->getRaw();
                    } catch (NotFoundException) {
                        $results[$index]['objectWithEnvironment'] = null; // This revision doesn't exist in this environment, but it's ok.
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

        return $this->render("@$this->templateNamespace/environment/align.html.twig", [
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
            'environments' => $this->environmentService->getEnvironments(),
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
                $anotherObject = $this->environmentRepository->findBy([
                        'name' => $name,
                ]);

                if (0 == \count($anotherObject)) {
                    $environment = new Environment();
                    $environment->setName($name);
                    $environment->setAlias($name);
                    // TODO: setCircles
                    $environment->setManaged(false);

                    $this->environmentRepository->save($environment);

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
        /** @var Environment $environment */
        $environment = $this->environmentRepository->find($id);

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
                $this->fieldTypeRepository->save($contentType->getFieldType());
                $this->contentTypeRepository->delete($contentType);
            }
            $this->environmentRepository->delete($environment);
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
            if (!static::isValidName($environment->getName())) {
                $form->get('name')->addError(new FormError('Must respects the following regex /^[a-z][a-z0-9\-_]*$/'));
            }

            if ($form->isValid()) {
                $anotherObject = $this->environmentRepository->findBy([
                        'name' => $environment->getName(),
                ]);

                if (0 != \count($anotherObject)) {
                    // TODO: test name format
                    $form->get('name')->addError(new FormError('Another environment named '.$environment->getName().' already exists'));
                } else {
                    $environment->setAlias($this->instanceId.$environment->getName());
                    $environment->setManaged(true);

                    $this->environmentRepository->save($environment);

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

        return $this->render("@$this->templateNamespace/environment/add.html.twig", [
                'form' => $form->createView(),
        ]);
    }

    public function editAction(int $id, Request $request): Response
    {
        try {
            $environment = $this->environmentService->giveById($id);
        } catch (\Throwable $e) {
            throw $this->createNotFoundException($e->getMessage());
        }

        $form = $this->createForm(EditEnvironmentType::class, $environment, [
            'type' => (null !== $this->circlesObject && '' !== $this->circlesObject ? $this->circlesObject : null),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->environmentService->updateEnvironment($environment);

            $this->logger->notice('log.environment.updated', [
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
            ]);

            return $this->redirectToRoute('environment.index');
        }

        return $this->render("@$this->templateNamespace/environment/edit.html.twig", [
            'environment' => $environment,
            'form' => $form->createView(),
        ]);
    }

    public function viewAction(int $id): Response
    {
        /** @var Environment|null $environment */
        $environment = $this->environmentRepository->find($id);

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

        return $this->render("@$this->templateNamespace/environment/view.html.twig", [
                'environment' => $environment,
                'info' => $info,
        ]);
    }

    public function rebuild(int $id, Request $request): Response
    {
        /** @var Environment|null $environment */
        $environment = $this->environmentRepository->find($id);

        if (null === $environment) {
            throw new NotFoundHttpException('Unknown environment');
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

        return $this->render("@$this->templateNamespace/environment/rebuild.html.twig", [
                'environment' => $environment,
                'form' => $form->createView(),
        ]);
    }

    public function indexAction(Request $request): Response
    {
        try {
            $table = $this->dataTableFactory->create(EnvironmentDataTableType::class);
            $form = $this->createForm(TableType::class, $table, [
                'title_label' => 'view.environment.index.local_environment_label',
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                    switch ($action->getName()) {
                        case EntityTable::DELETE_ACTION:
                            $this->environmentService->deleteByIds($table->getSelected());
                            break;
                        case TableType::REORDER_ACTION:
                            $newOrder = TableType::getReorderedKeys($form->getName(), $request);
                            $this->environmentService->reorderByIds($newOrder);
                            break;
                        default:
                            $this->logger->error('log.controller.environment.unknown_action');
                    }
                } else {
                    $this->logger->error('log.controller.environment.unknown_action');
                }

                return $this->redirectToRoute('environment.index');
            }

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
            }

            return $this->render("@$this->templateNamespace/environment/index.html.twig", [
                'environments' => $environments,
                'orphanIndexes' => $this->aliasService->getOrphanIndexes(),
                'unreferencedAliases' => $this->aliasService->getUnreferencedAliases(),
                'managedAliases' => $this->aliasService->getManagedAliases(),
                'form' => $form->createView(),
            ]);
        } catch (NoNodesAvailableException) {
            return $this->redirectToRoute('elasticsearch.status');
        }
    }
}
