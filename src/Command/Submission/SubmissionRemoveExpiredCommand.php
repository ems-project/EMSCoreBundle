<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Submission;

use EMS\CoreBundle\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SubmissionRemoveExpiredCommand extends AbstractCommand
{
    private FormSubmissionService $formSubmissionService;

    protected static $defaultName = Commands::SUBMISSION_REMOVE_EXPIRED;

    public function __construct(FormSubmissionService $formSubmissionService)
    {
        parent::__construct();
        $this->formSubmissionService = $formSubmissionService;
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
