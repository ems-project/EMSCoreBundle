<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Dashboard;

use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    private LoggerInterface $logger;
    private DashboardManager $dashboardManager;

    public function __construct(LoggerInterface $logger, DashboardManager $dashboardManager)
    {
        $this->logger = $logger;
        $this->dashboardManager = $dashboardManager;
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
//                        $this->dashboardManager->deleteByIds($table->getSelected());
                        break;
                    case TableType::REORDER_ACTION:
                        $newOrder = TableType::getReorderedKeys($form->getName(), $request);
//                        $this->dashboardManager->reorderByIds($newOrder);
                        break;
                    default:
                        $this->logger->error('log.controller.channel.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.channel.unknown_action');
            }

            return $this->redirectToRoute('ems_core_channel_index');
        }

        return $this->render('@EMSCore/dashboard/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->dashboardManager, $this->generateUrl('emsco_dashboard_admin_index_ajax'));
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumn('channel.index.column.label', 'label');
        $table->addColumn('channel.index.column.name', 'name');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'channel.actions.delete_selected', 'channel.actions.delete_selected_confirm');
        $table->setDefaultOrder('orderKey');

        return $table;
    }
}
