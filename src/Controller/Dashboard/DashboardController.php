<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Dashboard;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\DataTable\Type\DashboardDataTableType;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\Dashboard\DashboardType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    ) {
    }

    public function index(Request $request): Page|RedirectResponse
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

        return new Page([
            'datatable' => ['form' => $form->createView()],
            'icon' => 'fa fa-dashboard',
            'title' => t('type.title_overview', ['type' => 'dashboard'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'dashboard'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    public function add(Request $request): Page|RedirectResponse
    {
        $dashboard = new Dashboard();
        $form = $this->createForm(DashboardType::class, $dashboard, [
            'create' => true,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->dashboardManager->update($dashboard);

            return $this->redirectToRoute(Routes::DASHBOARD_ADMIN_EDIT, ['dashboard' => $dashboard->getId()]);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_create', ['type' => 'dashboard'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'dashboard'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_create', ['type' => 'dashboard'], 'emsco-core')
            ),
        ]);
    }

    public function edit(Request $request, Dashboard $dashboard): Page|RedirectResponse
    {
        $form = $this->createForm(DashboardType::class, $dashboard);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->dashboardManager->update($dashboard);

            return $this->redirectToRoute(Routes::DASHBOARD_ADMIN_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_edit', ['type' => 'dashboard', 'label' => $dashboard->getLabel()], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'dashboard'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_edit', ['type' => 'dashboard', 'label' => $dashboard->getLabel()], 'emsco-core')
            ),
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

    private function breadcrumb(): Navigation
    {
        return Navigation::admin()->add(
            label: t('key.dashboards', [], 'emsco-core'),
            icon: 'fa fa-dashboard',
            route: 'emsco_dashboard_admin_index',
        );
    }
}
