<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Admin;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\DataTable\Type\Job\JobDataTableType;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\JobType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\JobService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

use function Symfony\Component\Translation\t;

class JobController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly JobService $jobService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LocalizedLoggerInterface $logger,
    ) {
    }

    public function add(Request $request, UserInterface $user): Page|RedirectResponse
    {
        $job = $this->jobService->newJob($user);
        $form = $this->createForm(JobType::class, $job);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->jobService->save($job);

            return $this->redirectToRoute(Routes::JOB_STATUS, ['job' => $job->getId()]);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_create', ['type' => 'job'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'job'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_create', ['type' => 'job'], 'emsco-core'),
            ),
        ]);
    }

    public function delete(Job $job): RedirectResponse
    {
        $this->jobService->delete($job);

        return $this->redirectToRoute(Routes::ADMIN_JOB_INDEX);
    }

    public function index(Request $request): Page|RedirectResponse
    {
        $table = $this->dataTableFactory->create(JobDataTableType::class);
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->jobService->deleteByIds(...$table->getSelected()),
                JobDataTableType::ACTION_DELETE_ALL => $this->jobService->clean(skipFailed: false),
                default => $this->logger->messageError(t('message.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::ADMIN_JOB_INDEX);
        }

        return new Page([
            'datatable' => ['form' => $form->createView(), 'table_id' => 'jobs'],
            'icon' => 'fa fa-file-text-o',
            'title' => t('type.title_overview', ['type' => 'job'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'job'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    public function relaunch(Job $job, UserInterface $user): RedirectResponse
    {
        $newJob = $this->jobService->newJob($user);
        $newJob->setCommand($job->getCommand());
        $newJob->setTag($job->getTag());

        $this->jobService->save($newJob);

        return $this->redirectToRoute('emsco_job_status', ['job' => $newJob->getId()]);
    }

    private function breadcrumb(): Navigation
    {
        return Navigation::admin()->add(
            label: t('key.jobs', [], 'emsco-core'),
            icon: 'fa fa-terminal',
            route: Routes::ADMIN_JOB_INDEX,
        );
    }
}
