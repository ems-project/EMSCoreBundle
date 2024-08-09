<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Admin;

use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\Environment\EnvironmentDataTableType;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\RebuildIndex;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\EditEnvironmentType;
use EMS\CoreBundle\Form\Form\RebuildIndexType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\FieldTypeRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\Mapping;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EnvironmentController extends AbstractController
{
    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly EnvironmentService $environmentService,
        private readonly ContentTypeService $contentTypeService,
        private readonly IndexService $indexService,
        private readonly Mapping $mapping,
        private readonly AliasService $aliasService,
        private readonly JobService $jobService,
        private readonly EnvironmentRepository $environmentRepository,
        private readonly FieldTypeRepository $fieldTypeRepository,
        private readonly ContentTypeRepository $contentTypeRepository,
        private readonly string $instanceId,
        private readonly ?string $circlesObject,
        private readonly string $templateNamespace,
        private readonly DataTableFactory $dataTableFactory)
    {
    }

    public function attach(string $name): Response
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

                    return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_EDIT, [
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

        return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
    }

    public function removeAlias(string $name): Response
    {
        if ($this->aliasService->removeAlias($name)) {
            $this->logger->notice('log.environment.alias_removed', [
                'alias' => $name,
            ]);
        }

        return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
    }

    public function remove(int $id): Response
    {
        /** @var Environment $environment */
        $environment = $this->environmentRepository->find($id);

        if (0 !== $environment->getRevisions()->count()) {
            $this->logger->error('log.environment.not_empty', [
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
            ]);

            return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
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

        return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
    }

    public static function isValidName(string $name): bool
    {
        return \preg_match('/^[a-z][a-z0-9\-_]*$/', $name) && \strlen($name) <= 100;
    }

    public function add(Request $request): Response
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

                    $this->environmentRepository->create($environment);

                    $indexName = $environment->getNewIndexName();
                    $this->mapping->createIndex($indexName, $this->environmentService->getIndexAnalysisConfiguration());

                    foreach ($this->contentTypeService->getAll() as $contentType) {
                        $this->contentTypeService->updateMapping($contentType, $indexName);
                    }

                    $this->indexService->updateAlias($environment->getAlias(), [], [$indexName]);

                    $this->logger->notice('log.environment.created', [
                        EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                    ]);

                    return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
                }
            }
        }

        return $this->render("@$this->templateNamespace/environment/add.html.twig", [
                'form' => $form->createView(),
        ]);
    }

    public function edit(int $id, Request $request): Response
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

            return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
        }

        return $this->render("@$this->templateNamespace/environment/edit.html.twig", [
            'environment' => $environment,
            'form' => $form->createView(),
        ]);
    }

    public function view(int $id): Response
    {
        /** @var Environment|null $environment */
        $environment = $this->environmentRepository->find($id);

        if (null === $environment) {
            throw new NotFoundHttpException('Unknow environment');
        }

        try {
            $info = $this->mapping->getMapping($environment);
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

    public function index(Request $request): Response
    {
        try {
            $table = $this->dataTableFactory->create(EnvironmentDataTableType::class, ['managed' => true]);
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

                return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
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
