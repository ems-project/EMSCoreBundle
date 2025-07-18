<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Job;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Job\ScheduleManager;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\DataTable\Type\Job\JobScheduleDataTableType;
use EMS\CoreBundle\Entity\Schedule;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\ScheduleType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        private readonly FlashMessageLogger $flashMessageLogger
    ) {
    }

    public function index(Request $request): Page|RedirectResponse
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

        return new Page([
            'datatable' => ['form' => $form->createView()],
            'icon' => 'fa fa-calendar-o',
            'title' => t('type.title_overview', ['type' => 'job_schedule'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'job_schedule'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    public function add(Request $request): Page|RedirectResponse
    {
        $schedule = new Schedule();

        $form = $this->createForm(ScheduleType::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->scheduleManager->update($schedule);
            $this->logger->notice('log.schedule.created', ['name' => $schedule->getName()]);

            return $this->redirectToRoute(Routes::SCHEDULE_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_create', ['type' => 'job_schedule'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'job_schedule'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_create', ['type' => 'job_schedule'], 'emsco-core')
            ),
        ]);
    }

    public function edit(Request $request, Schedule $schedule): Page|RedirectResponse|JsonResponse
    {
        $form = $this->createForm(ScheduleType::class, $schedule, [
            'ajax-save-url' => $this->generateUrl(Routes::SCHEDULE_EDIT, ['schedule' => $schedule->getId(), '_format' => 'json']),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->scheduleManager->update($schedule);
            $this->logger->notice('log.schedule.updated', ['name' => $schedule->getName()]);

            if ('json' === $request->getRequestFormat()) {
                return $this->flashMessageLogger->buildJsonResponse(['success' => true]);
            }

            return $this->redirectToRoute(Routes::SCHEDULE_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_edit', ['type' => 'job_schedule', 'label' => $schedule->getName()], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'job_schedule'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_edit', ['type' => 'job_schedule', 'label' => $schedule->getName()], 'emsco-core')
            ),
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

    private function breadcrumb(): Navigation
    {
        return Navigation::admin()->add(
            label: t('key.schedule', [], 'emsco-core'),
            icon: 'fa fa-calendar-o',
            route: 'emsco_schedule_index',
        );
    }
}
