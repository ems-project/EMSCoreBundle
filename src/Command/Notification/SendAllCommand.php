<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Notification;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\Notification;
use EMS\CoreBundle\Repository\NotificationRepository;
use EMS\CoreBundle\Service\NotificationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SendAllCommand extends Command
{
    protected static $defaultName = 'ems:notification:send';
    private Registry $doctrine;
    private NotificationService $notificationService;
    private string $notificationPendingTimeout;

    public function __construct(Registry $doctrine, NotificationService $notificationService, string $notificationPendingTimeout)
    {
        $this->doctrine = $doctrine;
        $this->notificationService = $notificationService;
        $this->notificationPendingTimeout = $notificationPendingTimeout;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Send all notifications and notification\'s responses emails')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Do not send emails, just a dry run'
            );
    }

    /**
     * @param Notification[] $resultSet
     */
    private function sendEmails(array $resultSet, OutputInterface $output): void
    {
        $count = \count($resultSet);
        $progress = new ProgressBar($output, $count);
        if (!$output->isVerbose()) {
            $progress->start();
        }

        foreach ($resultSet as $idx => $item) {
            if ($output->isVerbose()) {
                $output->writeln(($idx + 1).'/'.$count.' : '.$item.' for '.$item->getRevision());
            }

            $this->notificationService->sendEmail($item);
            if (!$output->isVerbose()) {
                $progress->advance();
            }
        }
        if (!$output->isVerbose()) {
            $progress->finish();
            $output->writeln('');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Sending pending notification and response emails to enabled users');

        $this->notificationService->setDryRun((bool) $input->getOption('dry-run'));

        $em = $this->doctrine->getManager();
        $notificationRepository = $em->getRepository(Notification::class);
        if (!$notificationRepository instanceof NotificationRepository) {
            throw new \RuntimeException('Unexpected repository');
        }

        $notifications = $notificationRepository->findBy([
                'status' => 'pending',
                'emailed' => null,
        ]);
        if (!empty($notifications)) {
            $output->writeln('Sending new notifications');
            $this->sendEmails($notifications, $output);
        }

        $date = new \DateTime();
        $date->sub(new \DateInterval($this->notificationPendingTimeout));
        $notifications = $notificationRepository->findReminders($date);

        if (!empty($notifications)) {
            $output->writeln('Sending reminders');
            $this->sendEmails($notifications, $output);
        }

        $notifications = $notificationRepository->findResponses();
        if (!empty($notifications)) {
            $output->writeln('Sending responses');
            $this->sendEmails($notifications, $output);
        }

        return 0;
    }
}
