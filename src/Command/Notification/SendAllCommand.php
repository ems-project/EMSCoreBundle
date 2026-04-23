<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Notification;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Command\AbstractCoreCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Notification;
use EMS\CoreBundle\Repository\NotificationRepository;
use EMS\CoreBundle\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: Commands::NOTIFICATION_SEND, description: "Send all notifications and notification's responses emails.", aliases: ['ems:notification:send'], hidden: false)]
final class SendAllCommand extends AbstractCoreCommand
{
    public function __construct(private readonly Registry $doctrine, private readonly NotificationService $notificationService, private readonly string $notificationPendingTimeout)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
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
        if (!$this->output->isVerbose()) {
            $progress->start();
        }

        foreach ($resultSet as $idx => $item) {
            if ($this->output->isVerbose()) {
                $this->io->text(($idx + 1).'/'.$count.' : '.$item.' for '.$item->getRevision());
            }

            $this->notificationService->sendEmail($item);
            if (!$this->output->isVerbose()) {
                $progress->advance();
            }
        }
        if (!$this->output->isVerbose()) {
            $progress->finish();
            $this->io->newLine();
        }
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->text('Sending pending notification and response emails to enabled users');

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
        if ([] !== $notifications) {
            $this->io->text('Sending new notifications');
            $this->sendEmails($notifications, $output);
        }

        $date = new \DateTime();
        $date->sub(new \DateInterval($this->notificationPendingTimeout));

        $notifications = $notificationRepository->findReminders($date);

        if ([] !== $notifications) {
            $this->io->text('Sending reminders');
            $this->sendEmails($notifications, $output);
        }

        $notifications = $notificationRepository->findResponses();
        if ([] !== $notifications) {
            $this->io->text('Sending responses');
            $this->sendEmails($notifications, $output);
        }

        return 0;
    }
}
