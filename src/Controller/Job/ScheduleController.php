<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Job;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Job\ScheduleManager;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\DataTable\Type\Job\JobScheduleDataTableType;
use EMS\CoreBundle\Entity\Schedule;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\ScheduleType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

final class ScheduleController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly ScheduleManager $scheduleManager,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LocalizedLoggerInterface $logger,
        private readonly FlashMessageLogger $flashMessageLogger,
        private readonly string $templateNamespace,
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(JobScheduleDataTableType::class);
        $form = $this->createForm(TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'job_schedule'], 'emsco-core'),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->scheduleManager->deleteByIds($table->getSelected()),
                TableType::REORDER_ACTION => $this->scheduleManager->reorderByIds(
                    ids: TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::SCHEDULE_INDEX);
        }

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-calendar-o',
            'title' => t('type.title_overview', ['type' => 'job_schedule'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'job_schedule'], 'emsco-core'),
            'breadcrumb' => [
                'admin' => t('key.admin', [], 'emsco-core'),
                'jobs' => t('key.jobs', [], 'emsco-core'),
                'page' => t('key.schedule', [], 'emsco-core'),
            ],
        ]);
    }

    public function add(Request $request): Response
    {
        $schedule = new Schedule();

        return $this->edit($request, $schedule, 'html', true, 'log.schedule.created', "@$this->templateNamespace/schedule/add.html.twig");
    }

    public function edit(Request $request, Schedule $schedule, string $_format, bool $create = false, string $logMessage = 'log.schedule.updated', ?string $template = null): Response
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
                return $this->flashMessageLogger->buildJsonResponse([
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
