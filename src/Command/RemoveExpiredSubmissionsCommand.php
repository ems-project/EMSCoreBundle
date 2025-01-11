<?php

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Command\CommandInterface;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::SUBMISSIONS_REMOVE_EXPIRED,
    description: 'Removes all form submissions that are expired.',
    hidden: false,
    aliases: ['ems:submissions:remove-expired']
)]
class RemoveExpiredSubmissionsCommand extends Command implements CommandInterface
{
    public function __construct(protected FormSubmissionService $formSubmissionService, protected LoggerInterface $logger)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $removedCount = $this->formSubmissionService->removeExpiredSubmissions();

        $this->logger->notice(\sprintf('%d submissions were removed', $removedCount));
        $output->writeln(\sprintf('%d submissions were removed', $removedCount));

        return 0;
    }
}
