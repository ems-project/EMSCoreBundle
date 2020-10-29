<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\DataService;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateMetaFieldCommand extends EmsCommand
{
    /** @var Registry  */
    protected $doctrine;
    /** @var DataService */
    protected $dataService;
    
    public function __construct(Registry $doctrine, Logger $logger, Client $client, DataService $dataService)
    {
        $this->doctrine = $doctrine;
        $this->dataService = $dataService;
        parent::__construct($logger, $client);
    }
    
    protected function configure(): void
    {
        $this
            ->setName('ems:environment:updatemetafield')
            ->setDescription('Update meta fields for all revisions of an environment')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Environment name'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (!\is_string($name)) {
            throw new \RuntimeException('Unexpected content type name');
        }
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var EnvironmentRepository $envRepo */
        $envRepo = $em->getRepository('EMSCoreBundle:Environment');
        /** @var RevisionRepository $revRepo */
        $revRepo = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Environment|null $environment */
        $environment = $envRepo->findOneBy(['name' => $name, 'managed' => true]);

        if ($environment === null) {
            $output->writeln(sprintf("WARNING: Environment named %s not found", $name));
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

                    $revision->setLockBy('SYSTEM_UPDATE_META');
                    $now = new \DateTime();
                    $until = $now->add(new \DateInterval("PT5M"));//+5 minutes
                    $revision->setLockUntil($until);

                    $em->persist($revision);
                     $progress->advance();
                    if ($progress->getProgress() % 20 == 0) {
                        $em->flush();
                    }
                } catch (NotLockedException $e) {
                    $output->writeln("<error>'.$e.'</error>");
                }
            }

            ++$page;
            $paginator = $revRepo->getRevisionsPaginatorPerEnvironment($environment, $page);
            $iterator = $paginator->getIterator();
        } while ($iterator instanceof \ArrayIterator && $iterator->count());

        $progress->finish();
        return 0;
    }
}
