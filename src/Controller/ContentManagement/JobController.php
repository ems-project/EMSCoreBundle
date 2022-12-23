<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Form\JobType;
use EMS\CoreBundle\Helper\EmsCoreResponse;
use EMS\CoreBundle\Service\JobService;
use Psr\Log\LoggerInterface;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use SensioLabs\AnsiConverter\Theme\Theme;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class JobController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger, private readonly JobService $jobService, private readonly int $pagingSize, private readonly bool $triggerJobFromWeb)
    {
    }

    public function index(Request $request): Response
    {
        $size = $this->pagingSize;
        $page = $request->query->getInt('page', 1);
        $from = ($page - 1) * $size;
        $total = $this->jobService->count();
        $lastPage = \ceil($total / $size);

        return $this->render('@EMSCore/job/index.html.twig', [
            'jobs' => $this->jobService->scroll($size, $from),
            'page' => $page,
            'size' => $size,
            'from' => $from,
            'lastPage' => $lastPage,
            'paginationPath' => 'job.index',
        ]);
    }

    public function jobStatus(Job $job): Response
    {
        $encoder = new Encoder();
        $theme = new Theme();
        $converter = new AnsiToHtmlConverter($theme);

        return $this->render('@EMSCore/job/status.html.twig', [
            'job' => $job,
            'status' => $encoder->encodeUrl($job->getStatus()),
            'output' => $encoder->encodeUrl($converter->convert($job->getOutput())),
            'launchJob' => true === $this->triggerJobFromWeb && false === $job->getStarted(),
        ]);
    }

    public function create(Request $request): Response
    {
        $form = $this->createForm(JobType::class, []);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof UserInterface) {
                throw new NotFoundHttpException('User not found');
            }

            $command = $form->get('command')->getData();
            $job = $this->jobService->createCommand($user, $command);

            return $this->redirectToRoute('job.status', [
                'job' => $job->getId(),
            ]);
        }

        return $this->render('@EMSCore/job/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function delete(Job $job): RedirectResponse
    {
        $this->jobService->delete($job);

        return $this->redirectToRoute('job.index');
    }

    public function clean(): RedirectResponse
    {
        $this->jobService->clean();

        return $this->redirectToRoute('job.index');
    }

    public function startJob(Job $job, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new NotFoundHttpException('User not found');
        }

        if ($job->getUser() != $user->getUsername()) {
            throw new AccessDeniedHttpException();
        }

        if ($job->getStarted() && $job->getDone()) {
            return new SymfonyJsonResponse('job already done');
        }

        if (false === $this->triggerJobFromWeb) {
            return EmsCoreResponse::createJsonResponse($request, true, [
                'message' => 'job is scheduled',
                'job_id' => $job->getId(),
            ]);
        }

        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $request->getSession()->save();
        }

        \set_time_limit(0);
        $this->jobService->run($job);
        $this->logger->notice('log.data.job.done', [
            'job_id' => $job->getId(),
        ]);

        return EmsCoreResponse::createJsonResponse($request, true, [
            'message' => 'job started',
            'job_id' => $job->getId(),
        ]);
    }
}
