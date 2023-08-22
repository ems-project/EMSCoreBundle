<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Dashboard;

use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\DashboardDataTableType;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Form\Dashboard\DashboardType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DashboardManager $dashboardManager,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(DashboardDataTableType::class);

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

        return $this->render("@$this->templateNamespace/dashboard/index.html.twig", [
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

        return $this->render($create ? "@$this->templateNamespace/dashboard/add.html.twig" : "@$this->templateNamespace/dashboard/edit.html.twig'", [
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
