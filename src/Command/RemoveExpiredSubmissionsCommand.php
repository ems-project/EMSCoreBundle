<?php

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Command\CommandInterface;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveExpiredSubmissionsCommand extends Command implements CommandInterface
{
    protected static $defaultName = 'ems:submissions:remove-expired';

    public function __construct(protected FormSubmissionService $formSubmissionService, protected LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Removes all form submissions that are expired');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $removedCount = $this->formSubmissionService->removeExpiredSubmissions();

        $this->logger->notice(\sprintf('%d submissions were removed', $removedCount));
        $output->writeln(\sprintf('%d submissions were removed', $removedCount));

        return 0;
    }
}
