<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Form\Form\JobType;
use EMS\CoreBundle\Helper\EmsCoreResponse;
use EMS\CoreBundle\Service\JobService;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use SensioLabs\AnsiConverter\Theme\Theme;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

class JobController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JobService $jobService,
        private readonly int $pagingSize,
        private readonly bool $triggerJobFromWeb,
        private readonly string $templateNamespace
    ) {
    }

    public function index(Request $request): Response
    {
        $size = $this->pagingSize;
        $page = $request->query->getInt('page', 1);
        $from = ($page - 1) * $size;
        $total = $this->jobService->count();
        $lastPage = \ceil($total / $size);

        return $this->render("@$this->templateNamespace/job/index.html.twig", [
            'jobs' => $this->jobService->scroll($size, $from),
            'page' => $page,
            'size' => $size,
            'from' => $from,
            'lastPage' => $lastPage,
            'paginationPath' => 'job.index',
        ]);
    }

    public function jobStatus(Request $request, Job $job): Response
    {
        $encoder = new Encoder();
        $converter = new AnsiToHtmlConverter(new Theme());

        if ('json' === $request->getRequestFormat() || 'json' === $request->getContentType()) {
            $output = $request->query->getBoolean('output');

            return new JsonResponse([
                'status' => $job->getStatus(),
                'progress' => $job->getProgress(),
                'done' => $job->getDone(),
                'started' => $job->getStarted(),
                'output' => $output ? $encoder->encodeUrl($converter->convert($job->getOutput())) : null,
            ]);
        }

        return $this->render("@$this->templateNamespace/job/status.html.twig", [
            'job' => $job,
            'status' => $encoder->encodeUrl($job->getStatus()),
            'output' => $encoder->encodeUrl($converter->convert($job->getOutput())),
            'launchJob' => true === $this->triggerJobFromWeb && false === $job->getStarted() && !$job->hasTag(),
        ]);
    }

    public function create(Request $request, UserInterface $user): Response
    {
        $job = $this->jobService->newJob($user);
        $form = $this->createForm(JobType::class, $job);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->jobService->save($job);

            return $this->redirectToRoute('job.status', ['job' => $job->getId()]);
        }

        return $this->render("@$this->templateNamespace/job/add.html.twig", ['form' => $form->createView()]);
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

    public function startJob(Job $job, Request $request, UserInterface $user): Response
    {
        if ($job->getUser() != $user->getUserIdentifier()) {
            throw new AccessDeniedHttpException();
        }

        if ($job->getStarted() && $job->getDone()) {
            return new JsonResponse('job already done');
        }

        if (false === $this->triggerJobFromWeb || $job->hasTag()) {
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

    public function startNextJob(Request $request, UserInterface $user, string $tag): Response
    {
        $job = $this->jobService->nextJob($tag);
        if (null === $job) {
            $job = $this->jobService->nextJobScheduled($user->getUserIdentifier(), $tag);
        }

        if (null === $job) {
            return EmsCoreResponse::createJsonResponse($request, true, ['message' => 'no next job']);
        }

        return EmsCoreResponse::createJsonResponse($request, true, [
            'message' => \sprintf('job %d flagged has started', $job->getId()),
            'job_id' => \strval($job->getId()),
            'command' => $job->getCommand(),
            'output' => $job->getOutput(),
        ]);
    }

    public function jobCompleted(Request $request, int $job): Response
    {
        $this->jobService->finish($job);

        return EmsCoreResponse::createJsonResponse($request, true);
    }

    public function jobFailed(Request $request, int $job): Response
    {
        $content = $request->getContent();
        if (!\is_string($content)) {
            throw new \RuntimeException('Unexpected non string content');
        }
        $data = Json::decode($content);
        $this->jobService->finish($job, $data['message'] ?? 'job failed');

        return EmsCoreResponse::createJsonResponse($request, true);
    }

    public function jobWrite(Request $request, int $job): Response
    {
        $content = $request->getContent();
        if (!\is_string($content)) {
            throw new \RuntimeException('Unexpected non string content');
        }
        $data = Json::decode($content);
        $message = \strval($data['message'] ?? '');
        $newLine = \boolval($data['new-line'] ?? false);
        $this->jobService->write($job, $message, $newLine);

        return EmsCoreResponse::createJsonResponse($request, true);
    }
}
