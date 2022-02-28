<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Common\Standard\Type;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Form\JobType;
use EMS\CoreBundle\Service\JobService;
use Psr\Log\LoggerInterface;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use SensioLabs\AnsiConverter\Theme\Theme;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class JobController extends AppController
{
    /**
     * @Route("/admin/job", name="job.index")
     */
    public function indexAction(Request $request, JobService $jobService): Response
    {
        $size = Type::integer($this->getParameter('ems_core.paging_size'));

        $page = $request->query->get('page', 1);
        $from = ($page - 1) * $size;
        $total = $jobService->count();
        $lastPage = \ceil($total / $size);

        return $this->render('@EMSCore/job/index.html.twig', [
            'jobs' => $jobService->scroll($size, $from),
            'page' => $page,
            'size' => $size,
            'from' => $from,
            'lastPage' => $lastPage,
            'paginationPath' => 'job.index',
        ]);
    }

    /**
     * @Route("/job/status/{job}", name="job.status")
     * @Route("/job/status/{job}", name="emsco_job_status")
     */
    public function jobStatusAction(Job $job, Encoder $encoder): Response
    {
        $theme = new Theme();
        $converter = new AnsiToHtmlConverter($theme);

        return $this->render('@EMSCore/job/status.html.twig', [
            'job' => $job,
            'status' => $encoder->encodeUrl($job->getStatus()),
            'output' => $encoder->encodeUrl($converter->convert($job->getOutput())),
            'launchJob' => true === $this->getParameter('ems_core.trigger_job_from_web') && false === $job->getStarted(),
        ]);
    }

    /**
     * @Route("/admin/job/add", name="job.add")
     */
    public function createAction(Request $request, JobService $jobService): Response
    {
        $form = $this->createForm(JobType::class, []);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof UserInterface) {
                throw new NotFoundHttpException('User not found');
            }

            $command = $form->get('command')->getData();
            $job = $jobService->createCommand($user, $command);

            return $this->redirectToRoute('job.status', [
                'job' => $job->getId(),
            ]);
        }

        return $this->render('@EMSCore/job/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/job/delete/{job}", name="job.delete", methods={"POST"})
     */
    public function deleteAction(Job $job, JobService $jobService): RedirectResponse
    {
        $jobService->delete($job);

        return $this->redirectToRoute('job.index');
    }

    /**
     * @Route("/admin/job/clean", name="job.clean", methods={"POST"})
     */
    public function cleanAction(JobService $jobService): RedirectResponse
    {
        $jobService->clean();

        return $this->redirectToRoute('job.index');
    }

    /**
     * @Route("/admin/job/start/{job}", name="job.start", methods={"POST"})
     * @Route("/job/start/{job}", name="emsco_job_start", methods={"POST"})
     */
    public function startJobAction(Job $job, Request $request, JobService $jobService, LoggerInterface $logger): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new NotFoundHttpException('User not found');
        }

        if ($job->getUser() != $user->getUsername()) {
            throw new AccessDeniedHttpException();
        }

        if ($job->getStarted() && $job->getDone()) {
            return new JsonResponse('job already done');
        }

        if (false === $this->getParameter('ems_core.trigger_job_from_web')) {
            return $this->returnJsonResponse($request, true, [
                'message' => 'job is scheduled',
                'job_id' => $job->getId(),
            ]);
        }

        $request->getSession()->save();
        \set_time_limit(0);
        $jobService->run($job);
        $logger->notice('log.data.job.done', [
            'job_id' => $job->getId(),
        ]);

        return $this->returnJsonResponse($request, true, [
            'message' => 'job started',
            'job_id' => $job->getId(),
        ]);
    }
}
