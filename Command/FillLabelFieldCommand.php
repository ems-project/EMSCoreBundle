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

class FillLabelFieldCommand extends EmsCommand
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
            ->setName('ems:environment:filllabelfield')
            ->setDescription('Fill all revisions field LabelField of an environment')
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

		/** @var JobRepository $envRepo */
		$envRepo = $em->getRepository('EMSCoreBundle:Environment');
		/** @var Environment $environment */
		$environment = $envRepo->findBy(['name' => $name, 'managed' => true]);
		if($environment && count($environment) == 1) {
			$environment = $environment[0];

			// create a new progress bar
			$progress = new ProgressBar($output, count($environment->getRevisions()));
			// start and displays the progress bar
			$progress->start();
			
			/** @var \EMS\CoreBundle\Entity\Revision $revision */
			foreach ($environment->getRevisions() as $revision) {
				try{
					$this->dataService->setMetaFields($revision);
//					echo $revision->getId()." => ".$revision->getLabelField()."\n";
					
					$query = 'UPDATE \EMS\CoreBundle\Entity\Revision r SET r.labelField = \''.str_replace ( "'", "&#39;", $revision->getLabelField()).'\' WHERE r.id = '.$revision->getId();
//					echo $query."\n";
 					$em->createQuery($query)->getResult();
// 					$em->persist($revision);
// 					$em->flush();
 					$progress->advance();
				}
				catch(NotLockedException $e){
					$output->writeln("<error>'.$e.'</error>");
				}			}
			$progress->finish();
		}
		else{
			$output->writeln("WARNING: Environment named ".$name." not found");
		}
    }
}