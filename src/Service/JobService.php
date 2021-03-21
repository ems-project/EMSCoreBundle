<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ObjectManager;
use EMS\CoreBundle\Command\JobOutput;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Repository\JobRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\HttpKernel\KernelInterface;

class JobService
{
    /** @var Registry */
    private $doctrine;
    /** @var ObjectManager */
    private $em;
    /** @var JobRepository */
    private $repository;
    /** @var KernelInterface */
    private $kernel;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(Registry $doctrine, KernelInterface $kernel, LoggerInterface $logger, JobRepository $jobRepository)
    {
        $this->doctrine = $doctrine;
        $this->em = $doctrine->getManager();
        $this->repository = $jobRepository;
        $this->kernel = $kernel;
        $this->logger = $logger;
    }

    public function clean(): void
    {
        $doneJobs = $this->repository->findBy(['done' => true]);
        foreach ($doneJobs as $doneJob) {
            $this->em->remove($doneJob);
        }

        $this->em->flush();
    }

    /**
     * @return Job[]
     */
    public function findByUser(string $user): array
    {
        $doneJobs = $this->repository->findBy([
            'user' => $user,
        ], [
            'created' => 'DESC',
        ], 20);

        return $doneJobs;
    }

    public function findNext(): ?Job
    {
        $job = $this->repository->findOneBy([
            'started' => false,
            'done' => false,
        ], [
            'created' => 'ASC',
        ]);

        if (null !== $job && !$job instanceof Job) {
            throw new \RuntimeException('Unexpected Job class object');
        }

        return $job;
    }

    public function count(): int
    {
        return $this->repository->countJobs();
    }

    public function countPending(): int
    {
        return $this->repository->countPendingJobs();
    }

    public function createCommand(UserInterface $user, ?string $command): Job
    {
        $job = $this->create($user);
        $job->setStatus('Job intialized');
        $job->setCommand($command);

        $this->em->persist($job);
        $this->em->flush();

        return $job;
    }

    public function delete(Job $job): void
    {
        $this->em->remove($job);
        $this->em->flush();
    }

    public function run(Job $job): void
    {
        $output = $this->start($job);

        try {
            $application = new Application($this->kernel);
            $application->setAutoExit(false);

            $command = (null === $job->getCommand() ? 'list' : $job->getCommand());
            $input = new ArgvInput(self::getArgv('console '.$command));

            $application->run($input, $output);
        } catch (\Exception $e) {
            $output->writeln('An exception has been raised!');
            $output->writeln('Exception:'.$e->getMessage());
        }

        $this->finish($job);
    }

    /**
     * @return Job[]
     */
    public function scroll(int $size, int $from): array
    {
        return $this->repository->findBy([], ['created' => 'DESC'], $size, $from);
    }

    public function start(Job $job): JobOutput
    {
        $output = new JobOutput($this->doctrine, $job);
        $output->setDecorated(true);
        $output->writeln('Job ready to be launch');

        $job->setStarted(true);

        $this->em->persist($job);
        $this->em->flush();

        return $output;
    }

    public function finish(Job $job): void
    {
        $job->setDone(true);
        $job->setProgress(100);

        $this->em->persist($job);
        $this->em->flush();

        $this->logger->info('Job '.$job->getCommand().' completed.');
    }

    /**
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
