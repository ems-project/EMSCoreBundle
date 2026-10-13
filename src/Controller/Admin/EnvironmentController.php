<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Admin;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\DataTable\Type\Environment\EnvironmentDataTableType;
use EMS\CoreBundle\DataTable\Type\Environment\EnvironmentManagedAliasDataTableType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\Environment\EnvironmentType;
use EMS\CoreBundle\Form\Form\Environment\RebuildType;
use EMS\CoreBundle\Form\Form\Environment\ViewEnvironmentType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\Mapping;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

use function Symfony\Component\Translation\t;

class EnvironmentController extends AbstractController
{
    use CoreControllerTrait;

    private Navigation $breadcrumb;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly EnvironmentService $environmentService,
        private readonly ContentTypeService $contentTypeService,
        private readonly IndexService $indexService,
        private readonly Mapping $mapping,
        private readonly JobService $jobService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly FormFactory $formFactory,
    ) {
        $this->breadcrumb = Navigation::admin()->environments();
    }

    public function add(Request $request): Page|RedirectResponse
    {
        $environment = new Environment();
        $form = $this->createForm(EnvironmentType::class, $environment, ['create' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->environmentService->create($environment);
            $indexName = $environment->getNewIndexName();
            $this->mapping->createIndex($indexName, $this->environmentService->getIndexAnalysisConfiguration());

            foreach ($this->contentTypeService->getAll() as $contentType) {
                $this->contentTypeService->updateMapping($contentType, $indexName);
            }

            $this->indexService->updateAlias($environment->getAlias(), [], [$indexName]);

            return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_create', ['type' => 'environment'], 'emsco-core'),
            'notice' => t('message.environment_add_notice', [], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb->add(
                t('type.title_create', ['type' => 'environment'], 'emsco-core')
            ),
        ]);
    }

    public function delete(Environment $environment): Response
    {
        $this->environmentService->delete($environment);

        return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
    }

    public function edit(UserInterface $user, Environment $environment, Request $request): Page|RedirectResponse
    {
        $form = $this->createForm(EnvironmentType::class, $environment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->environmentService->updateEnvironment($environment);

            $this->logger->messageNotice(t('message.environment_updated', [
                'environment' => $environment->getLabel(),
            ], 'emsco-core'));

            return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_edit', ['type' => 'environment', 'label' => $environment->getLabel($user)], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb->add(
                t('type.title_edit', ['type' => 'environment', 'label' => $environment->getLabel($user)], 'emsco-core')
            ),
        ]);
    }

    public function index(Request $request): Page|RedirectResponse
    {
        $datatableEnvironment = $this->dataTableEnvironment($request);

        return match (true) {
            $datatableEnvironment instanceof RedirectResponse => $datatableEnvironment,
            default => new Page([
                'icon' => 'fa fa-list-ul',
                'title' => t('type.title_overview', ['type' => 'environment'], 'emsco-core'),
                'datatables' => [
                    [
                        'title' => t('key.environments_local', [], 'emsco-core'),
                        'icon' => 'fa fa-database',
                        'form' => $datatableEnvironment->createView(),
                        'table_id' => 'environments-local',
                    ],
                    [
                        'title' => t('key.environments_external', [], 'emsco-core'),
                        'icon' => 'fa fa-plug',
                        'form' => $this->dataTableExternalEnvironment()->createView(),
                        'table_id' => 'environments-external',
                    ],
                    [
                        'title' => t('key.managed_aliases', [], 'emsco-core'),
                        'icon' => 'fa fa-code-fork',
                        'form' => $this->dataTableManagedAlias()->createView(),
                        'table_id' => 'environments-managed-alias',
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

    public function rebuild(UserInterface $user, Environment $environment, Request $request): Page|RedirectResponse
    {
        $form = $this->createForm(RebuildType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $command = match ($form->get('option')->getData()) {
                'newIndex' => 'ems:environment:rebuild',
                'sameIndex' => 'ems:environment:reindex',
                default => throw new \RuntimeException('Unexpected rebuild option'),
            };
            $job = $this->jobService->createCommand($user, \sprintf('%s %s', $command, $environment->getName()));

            return $this->redirectToRoute(Routes::JOB_STATUS, ['job' => $job->getId()]);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('title.rebuild_environment', ['name' => $environment->getName()], 'emsco-core'),
            'notice' => t('message.environment_rebuild_notice', [], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb->add(
                t('title.rebuild_index_short', [], 'emsco-core')
            ),
        ]);
    }

    public function view(UserInterface $user, Environment $environment): Page
    {
        return new Page([
            'form' => $this->createForm(ViewEnvironmentType::class, $environment)->createView(),
            'title' => t('title.view_environment', ['label' => $environment->getLabel($user)], 'emsco-core'),
            'subTitle' => t('title.view_environment_short', [], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb->add(
                t('title.view_environment', ['label' => $environment->getLabel($user)], 'emsco-core')
            ),
        ]);
    }

    /**
     * @return RedirectResponse|FormInterface<mixed>
     */
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
                default => $this->logger->messageError(t('message.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
        }

        return $form;
    }

    /**
     * @return FormInterface<mixed>
     */
    private function dataTableExternalEnvironment(): FormInterface
    {
        $table = $this->dataTableFactory->create(EnvironmentDataTableType::class, ['managed' => false]);

        return $this->formFactory->createNamed('environment_external', TableType::class, $table, [
            'reorder_label' => false,
        ]);
    }

    /**
     * @return FormInterface<mixed>
     */
    private function dataTableManagedAlias(): FormInterface
    {
        $table = $this->dataTableFactory->create(EnvironmentManagedAliasDataTableType::class);

        return $this->formFactory->createNamed('environment_managed_alias', TableType::class, $table);
    }
}
