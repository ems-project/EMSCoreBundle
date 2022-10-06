<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Log;

use EMS\CommonBundle\Entity\Log;
use EMS\CoreBundle\Core\Log\LogEntityTableContext;
use EMS\CoreBundle\Core\Log\LogManager;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\UserTableColumn;
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

class LogController extends AbstractController
{
    private LoggerInterface $logger;
    private LogManager $logManager;

    public function __construct(LogManager $logManager, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logManager = $logManager;
    }

    public function index(Request $request, string $_format): Response
    {
        $table = $this->initTable();
        if ('json' === $_format) {
            $dataTableRequest = DataTableRequest::fromRequest($request);
            $table->resetIterator($dataTableRequest);

            return $this->render('@EMSCore/datatable/ajax.html.twig', [
                'dataTableRequest' => $dataTableRequest,
                'table' => $table,
            ], new JsonResponse());
        }

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::DELETE_ACTION:
                        $this->logManager->deleteByIds($table->getSelected());
                        break;
                    default:
                        $this->logger->error('log.controller.log.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.log.unknown_action');
            }

            return $this->redirectToRoute(Routes::LOG_INDEX);
        }

        return $this->render('@EMSCore/log/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function view(Log $log): Response
    {
        return $this->render('@EMSCore/log/view.html.twig', [
            'log' => $log,
        ]);
    }

    public function delete(Log $log): Response
    {
        $this->logManager->delete($log);

        return $this->redirectToRoute(Routes::LOG_INDEX);
    }

    private function initTable(): EntityTable
    {
        $table = new EntityTable(
            $this->logManager,
            $this->generateUrl(Routes::LOG_INDEX, ['_format' => 'json']),
            new LogEntityTableContext()
        );
        $table->setLabelAttribute('id');
        $table->addColumnDefinition(new DatetimeTableColumn('log.index.column.created', 'created'));
        $table->addColumn('log.index.column.channel', 'channel');
        $table->addColumn('log.index.column.level_name', 'levelName');
        $table->addColumn('log.index.column.message', 'message');
        $table->addColumnDefinition(new UserTableColumn('log.index.column.username', 'username'));
        $table->addColumnDefinition(new UserTableColumn('log.index.column.impersonator', 'impersonator'));
        $table->addItemGetAction(Routes::LOG_VIEW, 'view.actions.view', 'eye');
        $table->addItemPostAction(Routes::LOG_DELETE, 'view.actions.delete', 'trash', 'view.actions.delete_confirm')->setButtonType('outline-danger');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'log.actions.delete_selected', 'log.actions.delete_selected_confirm')
            ->setCssClass('btn btn-outline-danger');
        $table->setDefaultOrder('created', 'desc');

        return $table;
    }
}
