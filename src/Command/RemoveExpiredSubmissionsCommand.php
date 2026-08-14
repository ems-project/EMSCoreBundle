<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::SUBMISSIONS_REMOVE_EXPIRED,
    description: 'Removes all form submissions that are expired.',
    aliases: ['ems:submissions:remove-expired'],
    hidden: false
)]
class RemoveExpiredSubmissionsCommand extends AbstractCoreCommand
{
    private const string OPTION_METADATA = 'metadata';
    private bool $metadata;

    public function __construct(protected FormSubmissionService $formSubmissionService, protected LoggerInterface $logger)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                self::OPTION_METADATA,
                null,
                InputOption::VALUE_NONE,
                'Only the submitted data will be deleted. The metadata will be kept.'
            )
        ;
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->metadata = $this->getOptionBool(self::OPTION_METADATA);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $removedCount = $this->formSubmissionService->removeExpiredSubmissionAttachments();
        $this->logger->notice(\sprintf('%d submission attachments were removed', $removedCount));
        $this->io->text(\sprintf('%d submission attachments were removed', $removedCount));

        $removedCount = $this->formSubmissionService->removeExpiredSubmissions($this->metadata);
        if ($this->metadata) {
            $this->logger->notice(\sprintf('%d submission data were cleaned out', $removedCount));
            $this->io->text(\sprintf('%d submission data were cleaned out', $removedCount));
        } else {
            $this->logger->notice(\sprintf('%d submissions were removed', $removedCount));
            $this->io->text(\sprintf('%d submissions were removed', $removedCount));
        }

        return Command::SUCCESS;
    }
}
