<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Job;

use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Job\ScheduleManager;
use EMS\CoreBundle\DataTable\Type\JobScheduleDataTableType;
use EMS\CoreBundle\Entity\Schedule;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Form\ScheduleType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ScheduleController extends AbstractController
{
    public function __construct(
        private readonly ScheduleManager $scheduleManager,
        private readonly LoggerInterface $logger,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(JobScheduleDataTableType::class);

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

        return $this->render("@$this->templateNamespace/schedule/index.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function add(Request $request): Response
    {
        $schedule = new Schedule();

        return $this->edit($request, $schedule, 'html', true, 'log.schedule.created', "@$this->templateNamespace/schedule/add.html.twig");
    }

    public function edit(Request $request, Schedule $schedule, string $_format, bool $create = false, string $logMessage = 'log.schedule.updated', string $template = null): Response
    {
        if (null === $template) {
            $template = "@$this->templateNamespace/schedule/edit.html.twig";
        }
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
                return $this->render("@$this->templateNamespace/ajax/notification.json.twig", [
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
}
