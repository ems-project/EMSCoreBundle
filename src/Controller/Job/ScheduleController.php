<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Job;

use EMS\CoreBundle\Core\Job\ScheduleManager;
use EMS\CoreBundle\Entity\Schedule;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\ScheduleType;
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

final class ScheduleController extends AbstractController
{
    public function __construct(private readonly ScheduleManager $scheduleManager, private readonly LoggerInterface $logger)
    {
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
                        $this->scheduleManager->deleteByIds($table->getSelected());
                        break;
                    case TableType::REORDER_ACTION:
                        $newOrder = TableType::getReorderedKeys($form->getName(), $request);
                        $this->scheduleManager->reorderByIds($newOrder);
                        break;
                    default:
                        $this->logger->error('log.controller.schedule.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.schedule.unknown_action');
            }

            return $this->redirectToRoute(Routes::SCHEDULE_INDEX);
        }

        return $this->render('@EMSCore/schedule/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function add(Request $request): Response
    {
        $schedule = new Schedule();

        return $this->edit($request, $schedule, 'html', true, 'log.schedule.created', '@EMSCore/schedule/add.html.twig');
    }

    public function edit(Request $request, Schedule $schedule, string $_format, bool $create = false, string $logMessage = 'log.schedule.updated', string $template = '@EMSCore/schedule/edit.html.twig'): Response
    {
        $form = $this->createForm(ScheduleType::class, $schedule, [
            'create' => $create,
            'ajax-save-url' => $this->generateUrl(Routes::SCHEDULE_EDIT, ['schedule' => $schedule->getId(), '_format' => 'json']),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->scheduleManager->update($schedule);
            $this->logger->notice($logMessage, [
                'name' => $schedule->getName(),
            ]);

            if ('json' === $_format) {
                return $this->render('@EMSCore/ajax/notification.json.twig', [
                    'success' => true,
                ]);
            }

            return $this->redirectToRoute(Routes::SCHEDULE_INDEX);
        }

        return $this->render($template, [
            'form' => $form->createView(),
            'schedule' => $schedule,
        ]);
    }

    public function duplicate(Schedule $schedule): Response
    {
        $newSchedule = clone $schedule;
        $this->scheduleManager->update($newSchedule);

        return $this->redirectToRoute(Routes::SCHEDULE_EDIT, ['schedule' => $newSchedule->getId()]);
    }

    public function delete(Schedule $schedule): Response
    {
        $this->scheduleManager->delete($schedule);

        return $this->redirectToRoute(Routes::SCHEDULE_INDEX);
    }

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->scheduleManager, $this->generateUrl(Routes::SCHEDULE_INDEX, ['_format' => 'json']));
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumn('schedule.index.column.name', 'name');
        $table->addColumn('schedule.index.column.cron', 'cron');
        $table->addColumn('schedule.index.column.command', 'command');
        $table->addColumnDefinition(new DatetimeTableColumn('schedule.index.column.previous-run', 'previousRun'));
        $table->addColumnDefinition(new DatetimeTableColumn('schedule.index.column.next-run', 'nextRun'));
        $table->addItemGetAction(Routes::SCHEDULE_EDIT, 'view.actions.edit', 'pencil');
        $table->addItemPostAction(Routes::SCHEDULE_DUPLICATE, 'view.actions.duplicate', 'pencil', 'view.actions.duplicate_confirm');
        $table->addItemPostAction(Routes::SCHEDULE_DELETE, 'view.actions.delete', 'trash', 'view.actions.delete_confirm')->setButtonType('outline-danger');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'schedule.actions.delete_selected', 'schedule.actions.delete_selected_confirm')
            ->setCssClass('btn btn-outline-danger');
        $table->setDefaultOrder('orderKey');

        return $table;
    }
}
