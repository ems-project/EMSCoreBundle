<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Job;

use EMS\CoreBundle\Core\Job\ScheduleManager;
use EMS\CoreBundle\Entity\Schedule;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Form\ScheduleType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Routes;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ScheduleController extends AbstractController
{
    private LoggerInterface $logger;
    private ScheduleManager $scheduleManager;

    public function __construct(ScheduleManager $scheduleManager, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->scheduleManager = $scheduleManager;
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

        return $this->render('@EMSCore/schedule/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function add(Request $request): Response
    {
        $schedule = new Schedule();

        $form = $this->createForm(ScheduleType::class, $schedule, [
            'create' => true,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->scheduleManager->update($schedule);

            $this->logger->notice('log.schedule.created', [
                'name' => $schedule->getName(),
            ]);

            return $this->redirectToRoute(Routes::SCHEDULE_INDEX);
        }

        return $this->render('@EMSCore/schedule/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->scheduleManager, $this->generateUrl(Routes::SCHEDULE_INDEX, ['_format' => 'json']));
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumn('schedule.index.column.name', 'name');

        return $table;
    }
}
