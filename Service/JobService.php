<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ObjectManager;
use EMS\CoreBundle\Command\JobOutput;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Repository\JobRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class JobService
{
    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @var ObjectManager
     */
    private $em;

    /**
     * @var JobRepository
     */
    private $repository;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Registry        $doctrine
     * @param KernelInterface $kernel
     * @param LoggerInterface $logger
     */
    public function __construct(Registry $doctrine, KernelInterface $kernel, LoggerInterface $logger, JobRepository $jobRepository)
    {
        $this->doctrine = $doctrine;
        $this->em = $doctrine->getManager();
        $this->repository = $jobRepository;
        $this->kernel = $kernel;
        $this->logger = $logger;
    }

    /**
     * Remove all done jobs
     */
    public function clean()
    {
        $doneJobs = $this->repository->findBy(['done' => true]);
        foreach ($doneJobs as $doneJob) {
            $this->em->remove($doneJob);
        }

        $this->em->flush();
    }

    public function findByUser(string $user) : array
    {
        $doneJobs = $this->repository->findBy([
            'user' => $user,
        ], [
            'created' => 'DESC',
        ], 20);
        return $doneJobs;
    }

    /**
     * @return int
     */
    public function count()
    {
        return (int)$this->repository->countJobs();
    }

    /**
     * @param UserInterface $user
     * @param string        $command
     *
     * @return Job
     */
    public function createCommand(UserInterface $user, $command = null)
    {
        $job = $this->create($user);
        $job->setStatus("Job intialized");
        $job->setCommand($command);

        $this->em->persist($job);
        $this->em->flush();

        return $job;
    }

    /**
     * @param UserInterface $user
     * @param string        $service
     * @param array         $arguments
     *
     * @return Job
     */
    public function createService(UserInterface $user, $service, array $arguments)
    {
        $job = $this->create($user);
        $job->setArguments($arguments);
        $job->setService($service);
        $job->setStatus("Job prepared");

        $this->em->persist($job);
        $this->em->flush();

        return $job;
    }

    /**
     * @param Job $job
     */
    public function delete(Job $job)
    {
        $this->em->remove($job);
        $this->em->flush();
    }

    /**
     * @param Job $job
     */
    public function run(Job $job)
    {
        $output = $this->start($job);

        try {
            $application = new Application($this->kernel);
            $application->setAutoExit(false);

            $command = (null === $job->getCommand() ? 'list' : $job->getCommand());
            $input = new ArgvInput(self::getArgv('console ' . $command));

            $application->run($input, $output);
        } catch (\Exception $e) {
            $output->writeln('An exception has been raised!');
            $output->writeln('Exception:' . $e->getMessage());
        }

        $this->finish($job, $output);
    }

    /**
     * @param int $size
     * @param int $from
     *
     * @return array
     */
    public function scroll($size, $from)
    {
        return $this->repository->findBy([], ['created' => 'DESC'], $size, $from);
    }

    /**
     * @param Job $job
     *
     * @return JobOutput
     */
    public function start(Job $job)
    {
        $output = new JobOutput($this->doctrine, $job);
        $output->setDecorated(true);
        $output->writeln("Job ready to be launch");

        $job->setStarted(true);

        $this->em->persist($job);
        $this->em->flush();

        return $output;
    }

    /**
     * @param Job       $job
     * @param JobOutput $output
     */
    public function finish(Job $job, JobOutput $output)
    {
        $job->setDone(true);
        $job->setProgress(100);

        $this->em->persist($job);
        $this->em->flush();

        $output->writeln('Job done');
        $this->logger->info('Job ' . $job->getCommand() . ' completed.');
    }

    /**
     * @param UserInterface $user
     *
     * @return Job
     */
    private function create(UserInterface $user)
    {
        $job = new Job();
        $job->setUser($user->getUsername());
        $job->setDone(false);
        $job->setStarted(false);
        $job->setProgress(0);

        return $job;
    }

    /**
     * @param string $string
     *
     * @return mixed
     */
    private static function getArgv($string)
    {
        \preg_match_all('/(?<=^|\s)([\'"]?)(.+?)(?<!\\\\)\1(?=$|\s)/', $string, $ms);

        return $ms[2];
    }
}
