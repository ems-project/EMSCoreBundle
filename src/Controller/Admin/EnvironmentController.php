<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Admin;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\DataTable\Type\Environment\EnvironmentDataTableType;
use EMS\CoreBundle\DataTable\Type\Environment\EnvironmentManagedAliasDataTableType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\RebuildIndex;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\EditEnvironmentType;
use EMS\CoreBundle\Form\Form\RebuildIndexType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\Mapping;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class EnvironmentController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly EnvironmentService $environmentService,
        private readonly ContentTypeService $contentTypeService,
        private readonly IndexService $indexService,
        private readonly Mapping $mapping,
        private readonly JobService $jobService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly FormFactory $formFactory,
        private readonly ?string $circlesObject,
        private readonly string $templateNamespace,
    ) {
    }

    public function remove(Environment $environment): Response
    {
        $this->environmentService->delete($environment);

        return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
    }

    public static function isValidName(string $name): bool
    {
        return \preg_match('/^[a-z][a-z0-9\-_]*$/', $name) && \strlen($name) <= 100;
    }

    public function add(Request $request): Response
    {
        $form = $this->createFormBuilder([])->add('name', IconTextType::class, [
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
            $environmentName = $form->get('name')->getData();

            if (!static::isValidName($environmentName)) {
                $form->get('name')->addError(new FormError('Must respects the following regex /^[a-z][a-z0-9\-_]*$/'));
            }

            if ($form->isValid()) {
                $anotherObject = $this->environmentService->getByName($environmentName);

                if ($anotherObject) {
                    // TODO: test name format
                    $form->get('name')->addError(new FormError('Another environment named '.$environmentName.' already exists'));
                } else {
                    $environment = $this->environmentService->createEnvironment(
                        name: $environmentName,
                        color: $form->get('color')->getData()
                    );

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

    public function edit(Environment $environment, Request $request): Response
    {
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

    public function view(Environment $environment): Response
    {
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

    public function rebuild(Environment $environment, Request $request): Response
    {
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
        $datatableEnvironment = $this->dataTableEnvironment($request);

        return match (true) {
            $datatableEnvironment instanceof RedirectResponse => $datatableEnvironment,
            default => $this->render("@$this->templateNamespace/crud/overview.html.twig", [
                'icon' => 'fa fa-list-ul',
                'title' => t('type.title_overview', ['type' => 'environment'], 'emsco-core'),
                'datatables' => [
                    [
                        'title' => t('key.environments_local', [], 'emsco-core'),
                        'icon' => 'fa fa-database',
                        'form' => $datatableEnvironment->createView(),
                    ],
                    [
                        'title' => t('key.environments_external', [], 'emsco-core'),
                        'icon' => 'fa fa-plug',
                        'form' => $this->dataTableExternalEnvironment()->createView(),
                    ],
                    [
                        'title' => t('key.managed_aliases', [], 'emsco-core'),
                        'icon' => 'fa fa-code-fork',
                        'form' => $this->dataTableManagedAlias()->createView(),
                    ],
                ],
                'breadcrumb' => Navigation::admin()->environments()->add(
                    label: t('type.title_overview', ['type' => 'environment'], 'emsco-core'),
                    icon: 'fa fa-list-ul',
                    route: Routes::ADMIN_ENVIRONMENT_INDEX
                ),
            ]),
        };
    }

    private function dataTableEnvironment(Request $request): RedirectResponse|FormInterface
    {
        $table = $this->dataTableFactory->create(EnvironmentDataTableType::class, ['managed' => true]);
        $form = $this->formFactory->createNamed('environment', TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'environment'], 'emsco-core'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->environmentService->deleteByIds(...$table->getSelected()),
                TableType::REORDER_ACTION => $this->environmentService->reorderByIds(
                    ...TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
        }

        return $form;
    }

    private function dataTableExternalEnvironment(): FormInterface
    {
        $table = $this->dataTableFactory->create(EnvironmentDataTableType::class, ['managed' => false]);

        return $this->formFactory->createNamed('environment_external', TableType::class, $table, [
            'reorder_label' => false,
        ]);
    }

    private function dataTableManagedAlias(): FormInterface
    {
        $table = $this->dataTableFactory->create(EnvironmentManagedAliasDataTableType::class);

        return $this->formFactory->createNamed('environment_managed_alias', TableType::class, $table);
    }
}
