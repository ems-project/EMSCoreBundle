<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Form\JobType;
use EMS\CoreBundle\Service\JobService;
use Exception;
use Psr\Log\LoggerInterface;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use SensioLabs\AnsiConverter\Theme\Theme;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
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
     * @Route("/admin/job", name="job.index"))
     */
    public function indexAction(Request $request, JobService $jobService): Response
    {
        $size = $this->getParameter('ems_core.paging_size');

        $page = $request->query->get('page', 1);
        $from = ($page - 1) * $size;
        $total = $jobService->count();
        $lastPage = ceil($total / $size);

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
            'output' => $encoder->encodeUrl($converter->convert($job->getOutput()))
        ]);
    }

    /**
     * @Route("/admin/job/add", name="job.add"))
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
            'form' => $form->createView()
        ]);
    }

    /**
     * @param Job $job
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
        //http://blog.alterphp.com/2012/08/how-to-deal-with-asynchronous-request.html
        $request->getSession()->save();

        if ($job->getStarted() && $job->getDone()) {
            return new JsonResponse('job already done');
        }

        set_time_limit(0);
        if (null !== $job->getService()) {
            $output = $jobService->start($job);

            try {
                $output->writeln('Job running');
                /** @var CoreBundle\Command\EmsCommand $command */
                $command = $this->container->get($job->getService());
                $input = new ArrayInput($job->getArguments());
                $command->run($input, $output);
                $logger->notice('log.data.job.done', [
                    'job_id' => $job->getId(),
                ]);
            } catch (ServiceNotFoundException $e) {
                $output->writeln('<error>Service not found</error>');
            } catch (InvalidArgumentException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
            } catch (Exception $e) {
                $output->writeln('An exception has been raised!');
                $output->writeln('Exception:' . $e->getMessage());
            }

            $jobService->finish($job, $output);
        } else {
            $jobService->run($job);
            $logger->notice('log.data.job.done', [
                'job_id' => $job->getId(),
            ]);
        }

        return $this->returnJsonResponse($request, true, [
            'message' => 'job started',
            'job_id' => $job->getId(),
        ]);
    }
}
