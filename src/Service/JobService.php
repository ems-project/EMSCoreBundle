<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Command\JobOutput;
use EMS\CoreBundle\Core\Job\ScheduleManager;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Repository\JobRepository;
use EMS\Helpers\Standard\DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function Symfony\Component\String\u;

class JobService implements EntityServiceInterface
{
    private readonly ObjectManager $em;

    public function __construct(
        ManagerRegistry $doctrine,
        private readonly KernelInterface $kernel,
        private readonly LoggerInterface $logger,
        private readonly JobRepository $repository,
        private readonly ScheduleManager $scheduleManager,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
        $this->em = $doctrine->getManager();
    }

    public function deleteByIds(string ...$ids): void
    {
        $analyzers = $this->repository->getByIds(...$ids);
        foreach ($analyzers as $analyzer) {
            $this->repository->delete($analyzer);
        }
    }

    public function nextJob(?string $tag = null): ?Job
    {
        return $this->repository->findOneBy(
            ['started' => false, 'done' => false, 'tag' => $tag],
            ['created' => 'ASC']
        );
    }

    public function nextJobScheduled(string $username, ?string $tag): ?Job
    {
        $nextScheduled = $this->scheduleManager->findNext($tag);

        if (null === $nextScheduled) {
            return null;
        }

        return $this->initJob($username, $nextScheduled->getCommand(), $nextScheduled->givePreviousRun());
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
        /** @var Job[] $doneJobs */
        $doneJobs = $this->repository->findBy([
            'user' => $user,
        ], [
            'created' => 'DESC',
        ], 20);

        return $doneJobs;
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->repository->countJobs($searchValue);
    }

    public function countPending(): int
    {
        return $this->repository->countPendingJobs();
    }

    public function newJob(UserInterface $user): Job
    {
        $job = new Job();
        $job->setUser($user->getUserIdentifier());
        $job->setStatus('Job intialized');

        return $job;
    }

    public function createCommand(UserInterface $user, ?string $command, ?string $tag = null): Job
    {
        $job = $this->newJob($user);
        $job->setCommand($command);
        $job->setTag($tag);
        $this->save($job);

        return $job;
    }

    public function save(Job $job): void
    {
        $this->em->persist($job);
        $this->em->flush();
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

            $command = ($job->getCommand() ?? 'list');
            $escapedCommand = u($command)->replace('\\', '\\\\')->toString();

            $input = new StringInput($escapedCommand);

            $application->run($input, $output);
        } catch (\Exception $e) {
            $output->writeln('An exception has been raised!');
            $output->writeln('Exception:'.$e->getMessage());
        }

        $this->finish($job->getId());
    }

    /**
     * @return Job[]
     */
    public function scroll(int $size, int $from): array
    {
        /** @var Job[] $jobs */
        $jobs = $this->repository->findBy([], ['created' => 'DESC'], $size, $from);

        return $jobs;
    }

    public function start(Job $job): JobOutput
    {
        $job->setStarted(true);
        $this->repository->save($job);

        $output = new JobOutput($this->repository, $job->getId());
        $output->setDecorated(true);
        $output->writeln('Job ready to be launch');

        return $output;
    }

    public function finish(int $jobId, ?string $errorMessage = null): void
    {
        $job = $this->repository->findById($jobId);
        $job->setDone(true);
        $job->setProgress(100);
        if (null !== $errorMessage) {
            $job->setStatus('failed');
            $job->setOutput($job->getOutput().PHP_EOL.$errorMessage.PHP_EOL);
        }

        $this->em->persist($job);
        $this->em->flush();

        $this->logger->info('Job '.$job->getCommand().' completed.');
    }

    public function initJob(string $username, ?string $command, \DateTime $startDate): Job
    {
        $job = new Job();
        $job->setCommand($command);
        $job->setUser($username);
        $job->setStarted(true);
        $job->setDone(false);
        $job->setCreated($startDate);
        $job->setModified(new \DateTime());
        $job->setProgress(0);
        $this->em->persist($job);
        $this->em->flush();

        return $job;
    }

    public function cleanJob(string $username, string $stringTime): int
    {
        try {
            $olderDate = DateTime::create($stringTime);
        } catch (\Throwable $e) {
            $this->logger->warning(\sprintf('Invalid string to time format: %s (%s)', $stringTime, $e->getMessage()));

            return 0;
        }
        try {
            $jobsCleaned = $this->repository->clean($username, $olderDate);
        } catch (\Throwable $e) {
            $this->logger->warning(\sprintf('Error during cleaning jobs: %s', $e->getMessage()));

            return 0;
        }

        if ($jobsCleaned > 0) {
            $this->logger->notice(\sprintf('%d scheduled jobs have been cleaned', $jobsCleaned));
        }

        return $jobsCleaned;
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    /**
     * @return Job[]
     */
    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        return $this->repository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'job';
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'jobs',
            'Job',
            'Jobs',
        ];
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        try {
            return $this->repository->findById(\intval($name));
        } catch (\Throwable) {
            return null;
        }
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('Job entities doesn\'t support JSON update');
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        if (null !== $name) {
            throw new \RuntimeException('Job entities doesn\'t support JSON update');
        }
        $meta = JsonClass::fromJsonString($json);
        $job = $meta->jsonDeserialize();
        if (!$job instanceof Job) {
            throw new \RuntimeException('Unexpected non Job object');
        }
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            throw new \RuntimeException('Unexpected null token');
        }
        $job->setUser($token->getUserIdentifier());
        $this->repository->save($job);

        return $job;
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $job = $this->repository->findById(\intval($name));
        $id = $job->getId();
        $this->repository->delete($job);

        return \strval($id);
    }

    public function write(int $jobId, string $message, bool $newLine): void
    {
        $job = $this->repository->findById($jobId);
        $job->setOutput($job->getOutput().$message.($newLine ? PHP_EOL : ''));

        $this->em->persist($job);
        $this->em->flush();

        $this->logger->info('Job '.$job->getCommand().' completed.');
    }
}
