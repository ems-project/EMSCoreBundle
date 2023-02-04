<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Dashboard;

use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Form\Form\DashboardType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Routes;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger, private readonly DashboardManager $dashboardManager)
    {
    }

    public function datatable(Request $request): Response
    {
        $table = $this->initTable();
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function index(Request $request): Response
    {
        $table = $this->initTable();

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::DELETE_ACTION:
                        $this->dashboardManager->deleteByIds($table->getSelected());
                        break;
                    case TableType::REORDER_ACTION:
                        $newOrder = TableType::getReorderedKeys($form->getName(), $request);
                        $this->dashboardManager->reorderByIds($newOrder);
                        break;
                    default:
                        $this->logger->error('log.controller.channel.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.channel.unknown_action');
            }

            return $this->redirectToRoute(Routes::DASHBOARD_ADMIN_INDEX);
        }

        return $this->render('@EMSCore/dashboard/index.html.twig', [
            'form' => $form->createView(),
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

        return $this->render($create ? '@EMSCore/dashboard/add.html.twig' : '@EMSCore/dashboard/edit.html.twig', [
            'form' => $form->createView(),
            'dashboard' => $dashboard,
        ]);
    }

    public function delete(Dashboard $dashboard): Response
    {
        $this->dashboardManager->delete($dashboard);

        return $this->redirectToRoute(Routes::DASHBOARD_ADMIN_INDEX);
    }

    public function setQuickSearch(Dashboard $dashboard): Response
    {
        $this->dashboardManager->setQuickSearch($dashboard);

        return $this->redirectToRoute(Routes::DASHBOARD_ADMIN_INDEX);
    }

    public function setLandingPage(Dashboard $dashboard): Response
    {
        $this->dashboardManager->setLandingPage($dashboard);

        return $this->redirectToRoute(Routes::DASHBOARD_ADMIN_INDEX);
    }

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->dashboardManager, $this->generateUrl(Routes::DASHBOARD_ADMIN_INDEX_AJAX));
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumn('dashboard.index.column.name', 'name');
        $table->addColumn('dashboard.index.column.label', 'label')->setItemIconCallback(fn (Dashboard $dashboard) => $dashboard->getIcon());
        $table->addColumnDefinition(new TemplateBlockTableColumn('dashboard.index.column.type', 'type', '@EMSCore/dashboard/columns.html.twig'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('dashboard.index.column.landing_page', 'landing_page', '@EMSCore/dashboard/columns.html.twig'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('dashboard.index.column.quick_search', 'quick_search', '@EMSCore/dashboard/columns.html.twig'));
        $table->addItemGetAction(Routes::DASHBOARD_ADMIN_EDIT, 'dashboard.actions.edit', 'pencil');

        $defineAction = $table->addItemActionCollection('dashboard.actions.define.title', 'gear');
        $defineAction->addItemPostAction(Routes::DASHBOARD_ADMIN_SET_LANDING_PAGE, 'dashboard.actions.define.set_landing_page', 'dot-circle-o');
        $defineAction->addItemPostAction(Routes::DASHBOARD_ADMIN_SET_QUICK_SEARCH, 'dashboard.actions.define.set_quick_search', 'search');

        $table->addItemPostAction(Routes::DASHBOARD_ADMIN_DELETE, 'dashboard.actions.delete', 'trash', 'dashboard.actions.delete_confirm')->setButtonType('outline-danger');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'dashboard.actions.delete_selected', 'dashboard.actions.delete_selected_confirm')
            ->setCssClass('btn btn-outline-danger');
        $table->setDefaultOrder('orderKey');

        return $table;
    }
}
