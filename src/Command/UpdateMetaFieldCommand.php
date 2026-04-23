<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\DataService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: Commands::ENVIRONMENT_UPDATE_META_FIELD, description: 'Update meta fields for all revisions of an environment.', aliases: ['ems:environment:updatemetafield'], hidden: false)]
class UpdateMetaFieldCommand extends AbstractCoreCommand
{
    private const string DEFAULT_USERNAME = 'SYSTEM_UPDATE_META';

    public function __construct(protected Registry $doctrine, protected LoggerInterface $logger, protected DataService $dataService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Environment name'
            );
        $this->addUsernameOption(self::DEFAULT_USERNAME);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (!\is_string($name)) {
            throw new \RuntimeException('Unexpected content type name');
        }
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var EnvironmentRepository $envRepo */
        $envRepo = $em->getRepository(Environment::class);
        /** @var RevisionRepository $revRepo */
        $revRepo = $em->getRepository(Revision::class);
        /** @var Environment|null $environment */
        $environment = $envRepo->findOneBy(['name' => $name, 'managed' => true]);

        if (null === $environment) {
            $this->io->warning(\sprintf('Environment named %s not found', $name));

            return -1;
        }

        $page = 0;
        $paginator = $revRepo->getRevisionsPaginatorPerEnvironment($environment, $page);

        $progress = new ProgressBar($output, $paginator->count());
        $progress->start();

        do {
            /** @var Revision $revision */
            foreach ($paginator as $revision) {
                try {
                    $this->dataService->setMetaFields($revision);

                    $revision->setLockBy($this->getUsername());
                    $now = new \DateTime();
                    $until = $now->add(new \DateInterval('PT5M')); // +5 minutes
                    $revision->setLockUntil($until);

                    $em->persist($revision);
                    $progress->advance();
                    if (0 === $progress->getProgress() % 20) {
                        $em->flush();
                    }
                } catch (NotLockedException $e) {
                    $this->io->error($e->getMessage());
                }
            }

            ++$page;
            $paginator = $revRepo->getRevisionsPaginatorPerEnvironment($environment, $page);
            $iterator = $paginator->getIterator();
        } while ($iterator instanceof \ArrayIterator && $iterator->count());

        $em->flush();
        $progress->finish();
        $this->dataService->unlockAllRevisions($this->getUsername());

        return 0;
    }
}
