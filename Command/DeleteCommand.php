<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use EMS\CoreBundle\Repository\RevisionRepository;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use EMS\CoreBundle\Repository\NotificationRepository;
use Symfony\Component\Console\Helper\ProgressBar;

class DeleteCommand extends ContainerAwareCommand
{
    protected $client;
    protected $mapping;
    protected $doctrine;
    protected $logger;
    protected $container;
    
    public function __construct(Registry $doctrine, Logger $logger, Client $client, $mapping, $container)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->client = $client;
        $this->mapping = $mapping;
        $this->container = $container;
        parent::__construct();
    }
    
    protected function configure()
    {
        $this
            ->setName('ems:contenttype:delete')
            ->setDescription('Delete all instances of a content type ')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Content type name'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var  Client $client */
        $client = $this->client;
        $name = $input->getArgument('name');
        /** @var ContentTypeRepository $ctRepo */
        $ctRepo = $em->getRepository('EMSCoreBundle:ContentType');
        /** @var ContentType $contentType */
        $contentType = $ctRepo->findOneBy([
                'name' => $name,
                'deleted'=> 0
                
        ]);
        if ($contentType) {
            /** @var RevisionRepository $revRepo */
            $revRepo = $em->getRepository('EMSCoreBundle:Revision');
            
            /** @var NotificationRepository $notRepo */
            $notRepo = $em->getRepository('EMSCoreBundle:Notification');
            
            $counter = 0;
            $total = $revRepo->countByContentType($contentType);
            if ($total == 0) {
                $output->writeln("Content type \"".$name."\" is already empty");
            } else {
                // create a new progress bar
                $progress = new ProgressBar($output, $total);
                // start and displays the progress bar
                $progress->start();
                
                while ($revRepo->countByContentType($contentType) > 0) {
                    $revisions = $revRepo->findByContentType($contentType, null, 20);
                    /**@var \EMS\CoreBundle\Entity\Revision $revision */
                    foreach ($revisions as $revision) {
                        foreach ($revision->getEnvironments() as $environment) {
                            try {
                                $client->delete([
                                        'index' => $environment->getAlias(),
                                        'type' => $contentType->getName(),
                                        'id' => $revision->getOuuid(),
                                ]);
                            } catch (Missing404Exception $e) {
                                //Deleting something that is not present shouldn't make problem.
                            }
                            $revision->removeEnvironment($environment);
                        }
                        ++$counter;
                        $notifications = $notRepo->findBy([
                            'revision' => $revision,
                        ]);
                        foreach ($notifications as $notification) {
                            $em->remove($notification);
                        }
                        
                        $em->remove($revision);

                        // advance the progress bar 1 unit
                        $progress->advance();
                        $em->flush();
//                         $em->clear($revision);
                    }
                    
                    unset($revisions);
                }
                

                // ensure that the progress bar is at 100%
                $progress->finish();
                $output->writeln(" deleting content type ".$name);
            }
        } else {
                $output->writeln("Content type ".$name." not found");
        }
    }
}
