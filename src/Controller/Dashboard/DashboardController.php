<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Dashboard;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\DashboardDataTableType;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\Dashboard\DashboardType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class DashboardController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly DashboardManager $dashboardManager,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace,
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(DashboardDataTableType::class);

        $form = $this->createForm(TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'dashboard'], 'emsco-core'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->dashboardManager->deleteByIds($table->getSelected()),
                TableType::REORDER_ACTION => $this->dashboardManager->reorderByIds(
                    ids: TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::DASHBOARD_ADMIN_INDEX);
        }

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-dashboard',
            'title' => t('type.title_overview', ['type' => 'dashboard'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'dashboard'], 'emsco-core'),
            'breadcrumb' => [
                'admin' => t('key.admin', [], 'emsco-core'),
                'page' => t('key.dashboards', [], 'emsco-core'),
            ],
        ]);
    }

    public function add(Request $request): Response
    {
        $dashboard = new Dashboard();

        return $this->edit($request, $dashboard, true);
    }

    public function edit(Request $request, Dashboard $dashboard, bool $create = false): Response
    {
        $form = $this->createForm(DashboardType::class, $dashboard, [
            'create' => $create,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->dashboardManager->update($dashboard);

            if ($create) {
                return $this->redirectToRoute(Routes::DASHBOARD_ADMIN_EDIT, ['dashboard' => $dashboard->getId()]);
            }

            return $this->redirectToRoute(Routes::DASHBOARD_ADMIN_INDEX);
        }

        return $this->render($create ? "@$this->templateNamespace/dashboard/add.html.twig" : "@$this->templateNamespace/dashboard/edit.html.twig", [
            'form' => $form->createView(),
            'dashboard' => $dashboard,
        ]);
    }

    public function delete(Dashboard $dashboard): Response
    {
        $this->dashboardManager->delete($dashboard);

        return $this->redirectToRoute(Routes::DASHBOARD_ADMIN_INDEX);
    }

    public function define(Dashboard $dashboard, string $definition): Response
    {
        $this->dashboardManager->define($dashboard, $definition);

        return $this->redirectToRoute(Routes::DASHBOARD_ADMIN_INDEX);
    }

    public function undefine(Dashboard $dashboard): Response
    {
        $this->dashboardManager->undefine($dashboard);

        return $this->redirectToRoute(Routes::DASHBOARD_ADMIN_INDEX);
    }
}
