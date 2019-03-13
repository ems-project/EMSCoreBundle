<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Repository\JobRepository;
use EMS\CoreBundle\Service\DataService;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Repository\EnvironmentRepository;

class UpdateMetaFieldCommand extends EmsCommand
{
    protected $doctrine;
    /**@var DataService */
    protected $dataService;
    
    public function __construct(Registry $doctrine, Logger $logger, Client $client, Session $session, DataService $dataService)
    {
        $this->doctrine = $doctrine;
        $this->dataService = $dataService;
        parent::__construct($logger, $client, $session);
    }
    
    protected function configure()
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var EnvironmentRepository $envRepo */
        $envRepo = $em->getRepository('EMSCoreBundle:Environment');
        /** @var RevisionRepository $revRepo */
        $revRepo = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Environment $environment */
        $environment = $envRepo->findBy(['name' => $name, 'managed' => true]);
        if ($environment && count($environment) == 1) {
            $environment = $environment[0];
            
            $page = 0;
            $paginator = $revRepo->getRevisionsPaginatorPerEnvironment($environment, $page);

            // create a new progress bar
            $progress = new ProgressBar($output, $paginator->count());
            // start and displays the progress bar
            $progress->start();
            
            do {
                /** @var \EMS\CoreBundle\Entity\Revision $revision */
                foreach ($paginator as $revision) {
                    try {
                        $this->dataService->setMetaFields($revision);
                        
                        $revision->setLockBy('SYSTEM_UPDATE_META');
                        $now = new \DateTime();
                        $until = $now->add(new \DateInterval("PT5M"));//+5 minutes
                        $revision->setLockUntil($until);
                        
    //                    echo $revision->getId()." => ".$revision->getLabelField()."\n";
                        
    //                     $query = 'UPDATE \EMS\CoreBundle\Entity\Revision r SET r.labelField = \''.str_replace ( "'", "&#39;", $revision->getLabelField()).'\' WHERE r.id = '.$revision->getId();
    //                    echo $query."\n";
    //                      $em->createQuery($query)->getResult();
    //                     $em->persist($revision);
    //                     $em->flush();
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
            } while ($paginator->getIterator()->count());
            
            $progress->finish();
        } else {
            $output->writeln("WARNING: Environment named ".$name." not found");
        }
    }
}
