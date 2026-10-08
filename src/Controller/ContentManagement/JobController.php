<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Helper\EmsCoreResponse;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\JobService;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use SensioLabs\AnsiConverter\Theme\Theme;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

use function Symfony\Component\Translation\t;

class JobController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly JobService $jobService,
        private readonly LocalizedLoggerInterface $logger,
        private readonly bool $triggerJobFromWeb,
    ) {
    }

    public function jobStatus(Request $request, Job $job): JsonResponse|Page
    {
        $encoder = new Encoder();
        $converter = new AnsiToHtmlConverter(new Theme());
        $jobOutput = $job->getOutput();

        if ('json' === $request->getRequestFormat() || 'json' === $request->getContentTypeFormat()) {
            $output = $request->query->getBoolean('output');

            return new JsonResponse([
                'status' => $job->getStatus(),
                'progress' => $job->getProgress(),
                'done' => $job->getDone(),
                'started' => $job->getStarted(),
                'output' => $output && $jobOutput ? $encoder->encodeUrl($converter->convert($jobOutput)) : null,
            ]);
        }

        return new Page(
            context: [
                'title' => t('type.title_status', ['type' => 'job', 'job_id' => $job->getId()], 'emsco-core'),
                'subTitle' => t('type.title_sub', ['type' => 'job'], 'emsco-core'),
                'job' => $job,
                'status' => $encoder->encodeUrl($job->getStatus()),
                'output' => $jobOutput ? $encoder->encodeUrl($converter->convert($jobOutput)) : null,
                'launchJob' => $this->triggerJobFromWeb && false === $job->getStarted() && !$job->hasTag(),
                'breadcrumb' => $this->breadcrumb()->add(
                    t('type.title_status', ['type' => 'job', 'job_id' => $job->getId()], 'emsco-core'),
                ),
            ],
            template: 'page/job_status.html.twig',
        );
    }

    public function startJob(Job $job, Request $request, UserInterface $user): Response
    {
        if ($job->getUser() !== $user->getUserIdentifier()) {
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

        $this->logger->messageNotice(t('message.job_done', [
            'job_id' => $job->getId(),
        ], 'emsco-core'));

        return EmsCoreResponse::createJsonResponse($request, true, [
            'message' => 'job started',
            'job_id' => $job->getId(),
        ]);
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
