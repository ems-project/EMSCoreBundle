<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Entity\Notification;
use EMS\CoreBundle\Repository\NotificationRepository;
use EMS\CoreBundle\Service\NotificationService;
use EMS\CoreBundle\Service\UserService;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SendNotificationsCommand extends ContainerAwareCommand
{
    /**@var Registry $doctrine*/
    private $doctrine;
    /**@var Logger $logger*/
    private $logger;
    /**@var UserService $userService*/
    private $userService;
    /**@var NotificationService $notificationService*/
    private $notificationService;
    
    private $notificationPendingTimeout;
    
    public function __construct(Registry $doctrine, Logger $logger, UserService $userService, NotificationService $notificationService, $notificationPendingTimeout)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->userService = $userService;
        $this->notificationService = $notificationService;
        
        $this->notificationPendingTimeout = $notificationPendingTimeout;
        
        parent::__construct();
    }
    
    protected function configure()
    {
        $this
            ->setName('ems:notification:send')
            ->setDescription('Send all notifications and notification\'s responses emails')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Do not send emails, just a dry run'
            );
    }
    
    private function sendEmails(array $resultSet, OutputInterface $output)
    {
        $count = count($resultSet);
        $progress = new ProgressBar($output, $count);
        if (!$output->isVerbose()) {
            $progress->start();
        }
        
        /**@var Notification $item*/
        foreach ($resultSet as $idx => $item) {
            if ($output->isVerbose()) {
                $output->writeln(($idx+1).'/'.$count.' : '.$item.' for '.$item->getRevision());
            }
            
            $this->notificationService->sendEmail($item);
            if (!$output->isVerbose()) {
                $progress->advance();
            }
        }
        if (!$output->isVerbose()) {
            // ensure that the progress bar is at 100%
            $progress->finish();
            $output->writeln("");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Sending pending notification and response emails to enabled users');
        
        $this->notificationService->setOutput($output->isVerbose()?$output:null);
        $this->notificationService->setDryRun($input->getOption('dry-run'));
        
        $em = $this->doctrine->getManager();
        /**@var NotificationRepository $notificationRepository*/
        $notificationRepository = $em->getRepository('EMSCoreBundle:Notification');
        
        //Send all pending notification
        $notifications = $notificationRepository->findBy([
                'status' => 'pending',
                'emailed' => null,
        ]);
        if (!empty($notifications)) {
            $output->writeln('Sending new notifications');
            $this->sendEmails($notifications, $output);
        }
        
        //Send all reminders
        
        $date = new \DateTime();
        $date->sub(new \DateInterval($this->notificationPendingTimeout));
        $notifications = $notificationRepository->findReminders($date);
        
        if (!empty($notifications)) {
            $output->writeln('Sending reminders');
            $this->sendEmails($notifications, $output);
        }
        
        //Send all response
        $notifications = $notificationRepository->findResponses();
        if (!empty($notifications)) {
            $output->writeln('Sending responses');
            $this->sendEmails($notifications, $output);
        }
    }
}
