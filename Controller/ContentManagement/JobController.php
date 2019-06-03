<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Form\Form\JobType;
use Exception;
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
use Symfony\Component\Routing\Annotation\Route;

class JobController extends AppController
{
    /**
     * @param Request $request
     * @return Response
     * @Route("/admin/job", name="job.index"))
     */
    public function indexAction(Request $request)
    {
        $jobService = $this->getJobService();
        $size = $this->container->getParameter('ems_core.paging_size');

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
     * @param Job $job
     * @return Response
     * @Route("/job/status/{job}", name="job.status"))
     */
    public function jobStatusAction(Job $job)
    {
        $theme = new Theme();
        $converter = new AnsiToHtmlConverter($theme);

        return $this->render('@EMSCore/job/status.html.twig', [
            'job' => $job,
            'output' => $converter->convert($job->getOutput())
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse|Response
     * @Route("/admin/job/add", name="job.add"))
     */
    public function createAction(Request $request)
    {
        $form = $this->createForm(JobType::class, []);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $command = $form->get('command')->getData();
            $job = $this->getJobService()->createCommand($this->getUser(), $command);

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
     * @return RedirectResponse
     * @Route("/admin/job/delete/{job}", name="job.delete", methods={"POST"})
     */
    public function deleteAction(Job $job)
    {
        $this->getJobService()->delete($job);

        return $this->redirectToRoute('job.index');
    }

    /**
     * @return RedirectResponse
     * @Route("/admin/job/clean", name="job.clean", methods={"POST"})
     */
    public function cleanAction()
    {
        $this->getJobService()->clean();

        return $this->redirectToRoute('job.index');
    }

    /**
     * Ajax action called on the status page
     * @param Job $job
     * @param Request $request
     * @return JsonResponse
     *
     * @Route("/admin/job/start/{job}", name="job.start", methods={"POST"})
     */
    public function startJobAction(Job $job, Request $request)
    {
        if ($job->getUser() != $this->getUser()->getUsername()) {
            throw new AccessDeniedHttpException();
        }
        //http://blog.alterphp.com/2012/08/how-to-deal-with-asynchronous-request.html
        $request->getSession()->save();

        if ($job->getStarted() && $job->getDone()) {
            return new JsonResponse('job already done');
        }

        $jobService = $this->getJobService();

        if (null !== $job->getService()) {
            $output = $jobService->start($job);

            try {
                /** @var CoreBundle\Command\EmsCommand $command */
                $command = $this->container->get($job->getService());
                $input = new ArrayInput($job->getArguments());
                $command->run($input, $output);
                $output->writeln('Job done');
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
        }

        return new JsonResponse('job started');
    }

    /**
     * @return CoreBundle\Service\JobService
     */
    private function getJobService()
    {
        return $this->container->get('ems.service.job');
    }
}
